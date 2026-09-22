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
if (!is_array($input) || array_is_list($input) || array_key_exists('menu_id', $input)) {
    sendJsonResponse(['erreur' => 'Objet JSON attendu ; le menu ne peut pas être modifié.'], 400);
    exit;
}
$commandeId = filter_var($input['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$personnes = filter_var($input['nombre_personnes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
$date = $input['date_prestation'] ?? null;
$heure = $input['heure_livraison'] ?? null;
$lieu = $input['lieu_livraison'] ?? null;
$ville = $input['ville_livraison'] ?? null;
$dateValide = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date);
if ($dateValide) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateValide = $parsed !== false && $parsed->format('Y-m-d') === $date;
}
$heureValide = is_string($heure) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $heure);
if ($dateValide && $heureValide) {
    $timezone = new DateTimeZone('Europe/Paris');
    $prestation = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $heure, $timezone);
    $dateValide = $prestation !== false && $prestation->format('Y-m-d H:i') === $date . ' ' . $heure
        && $prestation > new DateTimeImmutable('now', $timezone);
}
if ($commandeId === false || $commandeId === null || $personnes === false || $personnes === null
    || !$dateValide || !$heureValide || !is_string($lieu) || trim($lieu) === '' || strlen(trim($lieu)) > 255
    || !is_string($ville) || trim($ville) === '' || strlen(trim($ville)) > 100) {
    sendJsonResponse(['erreur' => 'Données invalides : indiquez notamment une date et une heure futures.'], 422);
    exit;
}
try {
    require_once __DIR__ . '/../services/DeliveryCalculator.php';
    $delivery = calculateDelivery(trim($lieu), trim($ville));
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
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403);
        exit;
    }
    require_once __DIR__ . '/../config/database.php';
    $pdo->beginTransaction();
    $client = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id FOR UPDATE');
    $client->execute(['id' => $utilisateurId]);
    $account = $client->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool) $account['actif'] || $account['role'] !== 'utilisateur') {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Compte indisponible.'], 403);
        exit;
    }
    $commandeStmt = $pdo->prepare('SELECT menu_id, statut FROM commande WHERE commande_id = :id AND utilisateur_id = :utilisateur_id FOR UPDATE');
    $commandeStmt->execute(['id' => $commandeId, 'utilisateur_id' => $utilisateurId]);
    $commande = $commandeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$commande) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }
    if ($commande['statut'] !== 'en attente') {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Seule une commande en attente peut être modifiée.'], 409);
        exit;
    }
    $menuStmt = $pdo->prepare('SELECT nombre_personnes_min, prix FROM menu WHERE menu_id = :id');
    $menuStmt->execute(['id' => $commande['menu_id']]);
    $menu = $menuStmt->fetch(PDO::FETCH_ASSOC);
    $minimum = $menu ? (int) $menu['nombre_personnes_min'] : 0;
    if ($minimum < 1 || $personnes < $minimum || (float) ($menu['prix'] ?? -1) < 0) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Nombre de personnes ou tarif du menu invalide.'], 422);
        exit;
    }
    $prixMenu = round((float) $menu['prix'] * $personnes / $minimum, 2);
    $reduction = $personnes >= $minimum + 5 ? round($prixMenu * 0.10, 2) : 0.0;
    $prixTotal = number_format($prixMenu - $reduction + $delivery['fee'], 2, '.', '');
    $update = $pdo->prepare("UPDATE commande SET date_prestation = :date, heure_livraison = :heure, lieu_livraison = :lieu, ville_livraison = :ville, distance_km = :distance, frais_livraison = :frais, nombre_personnes = :personnes, prix_total = :prix WHERE commande_id = :id AND utilisateur_id = :utilisateur_id AND statut = 'en attente'");
    $update->execute([
        'date' => $date, 'heure' => $heure, 'lieu' => trim($lieu), 'ville' => trim($ville),
        'distance' => number_format($delivery['distance_km'], 2, '.', ''), 'frais' => number_format($delivery['fee'], 2, '.', ''), 'personnes' => $personnes,
        'prix' => $prixTotal, 'id' => $commandeId, 'utilisateur_id' => $utilisateurId,
    ]);
    $pdo->commit();
    require_once __DIR__ . '/../services/NoSqlStatistics.php';
    projectOrderToNoSql($pdo, $commandeId);
    sendJsonResponse(['message' => 'Commande en attente modifiée.', 'commande_id' => $commandeId, 'prix_menu' => number_format($prixMenu, 2, '.', ''), 'reduction' => number_format($reduction, 2, '.', ''), 'distance_km' => number_format($delivery['distance_km'], 2, '.', ''), 'frais_livraison' => number_format($delivery['fee'], 2, '.', ''), 'prix_total' => $prixTotal, 'statut' => 'en attente']);
} catch (DomainException $e) {
    sendJsonResponse(['erreur' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Échec de modification de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
