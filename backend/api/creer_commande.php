<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || strlen($body) > 16384) {
    sendJsonResponse(['erreur' => 'Requête invalide ou trop volumineuse.'], 400);
    exit;
}

try {
    $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
    exit;
}

if (!is_array($input) || array_is_list($input)) {
    sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
    exit;
}

$menuId = filter_var($input['menu_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$personnes = filter_var($input['nombre_personnes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
$date = $input['date_prestation'] ?? null;
$heure = $input['heure_livraison'] ?? null;
$lieu = $input['lieu_livraison'] ?? null;
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

$dateValide = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date);
if ($dateValide) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateValide = $parsed !== false && $parsed->format('Y-m-d') === $date && $date >= date('Y-m-d');
}

if ($menuId === false || $personnes === false || !$dateValide
    || !is_string($heure) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $heure)
    || !is_string($lieu) || trim($lieu) === '' || strlen(trim($lieu)) > 255) {
    sendJsonResponse(['erreur' => 'Données de commande invalides.'], 422);
    exit;
}

try {
    require_once __DIR__ . '/../config/session.php';
    startSecureSession();
    $utilisateurId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($utilisateurId) || $utilisateurId < 1) {
        sendJsonResponse(['erreur' => 'Authentification requise.'], 401);
        exit;
    }
    if (($_SESSION['role'] ?? null) !== 'utilisateur') {
        sendJsonResponse(['erreur' => 'Accès réservé aux clients.'], 403);
        exit;
    }
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403);
        exit;
    }

    require_once __DIR__ . '/../config/database.php';
    $pdo->beginTransaction();

    $client = $pdo->prepare('SELECT actif FROM utilisateur WHERE utilisateur_id = :id FOR UPDATE');
    $client->execute(['id' => $utilisateurId]);
    if (!(bool) $client->fetchColumn()) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Compte indisponible.'], 403);
        exit;
    }

    $menu = $pdo->prepare('SELECT nombre_personnes_min, prix, stock, disponible FROM menu WHERE menu_id = :id FOR UPDATE');
    $menu->execute(['id' => $menuId]);
    $details = $menu->fetch(PDO::FETCH_ASSOC);
    if (!$details || !(bool) $details['disponible'] || (int) $details['stock'] < 1) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Menu indisponible.'], 409);
        exit;
    }
    if ($personnes < (int) $details['nombre_personnes_min']) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Nombre de personnes inférieur au minimum du menu.'], 422);
        exit;
    }

    // Le tarif du menu correspond au minimum de personnes ; les convives
    // supplémentaires sont facturés au prorata, arrondi au centime.
    $prixTotal = number_format(round((float) $details['prix'] * $personnes / (int) $details['nombre_personnes_min'], 2), 2, '.', '');
    $insert = $pdo->prepare(
        'INSERT INTO commande (utilisateur_id, menu_id, date_prestation, heure_livraison, lieu_livraison, nombre_personnes, prix_total)
         VALUES (:utilisateur_id, :menu_id, :date_prestation, :heure_livraison, :lieu_livraison, :nombre_personnes, :prix_total)'
    );
    $insert->execute([
        'utilisateur_id' => $utilisateurId,
        'menu_id' => $menuId,
        'date_prestation' => $date,
        'heure_livraison' => $heure,
        'lieu_livraison' => trim($lieu),
        'nombre_personnes' => $personnes,
        'prix_total' => $prixTotal,
    ]);
    $commandeId = (int) $pdo->lastInsertId();
    $update = $pdo->prepare('UPDATE menu SET stock = stock - 1 WHERE menu_id = :id AND stock > 0');
    $update->execute(['id' => $menuId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Stock modifié pendant la commande.');
    }
    $pdo->commit();
    sendJsonResponse(['message' => 'Commande créée.', 'commande_id' => $commandeId, 'prix_total' => $prixTotal, 'statut' => 'accepté'], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Échec de création de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
