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
$ville = $input['ville_livraison'] ?? null;
$dateValide = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date);
if ($dateValide) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateValide = $parsed !== false && $parsed->format('Y-m-d') === $date;
}
$heureValide = is_string($heure) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $heure);
// Les dates et heures de livraison sont interprétées dans le fuseau du restaurant.
if ($dateValide && $heureValide) {
    $timezone = new DateTimeZone('Europe/Paris');
    $prestation = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $heure, $timezone);
    $dateValide = $prestation !== false
        && $prestation->format('Y-m-d H:i') === $date . ' ' . $heure
        && $prestation > new DateTimeImmutable('now', $timezone);
}
if ($menuId === false || $personnes === false || !$dateValide || !$heureValide
    || !is_string($lieu) || trim($lieu) === '' || strlen(trim($lieu)) > 255
    || !is_string($ville) || trim($ville) === '' || strlen(trim($ville)) > 100) {
    sendJsonResponse(['erreur' => 'Données de commande invalides : choisir notamment une date et une heure futures et indiquer ville_livraison.'], 422);
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
    $client = $pdo->prepare('SELECT actif, role, email, prenom FROM utilisateur WHERE utilisateur_id = :id FOR UPDATE');
    $client->execute(['id' => $utilisateurId]);
    $compte = $client->fetch(PDO::FETCH_ASSOC);
    if (!$compte || !(bool) $compte['actif'] || $compte['role'] !== 'utilisateur') {
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
    $minimum = (int) $details['nombre_personnes_min'];
    if ($minimum < 1 || $personnes < $minimum || (float) $details['prix'] < 0) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Nombre de personnes ou tarif du menu invalide.'], 422);
        exit;
    }
    // Prix du menu pour le minimum de convives, supplément proportionnel ; remise
    // de 10 % dès cinq convives supplémentaires. Frais Bordeaux : 0 €.
    $prixMenu = round((float) $details['prix'] * $personnes / $minimum, 2);
    $reduction = $personnes >= $minimum + 5 ? round($prixMenu * 0.10, 2) : 0.0;
    $prixTotal = number_format($prixMenu - $reduction + $delivery['fee'], 2, '.', '');
    $insert = $pdo->prepare(
        'INSERT INTO commande (utilisateur_id, menu_id, date_prestation, heure_livraison, lieu_livraison, ville_livraison, distance_km, frais_livraison, nombre_personnes, prix_total, statut)
         VALUES (:utilisateur_id, :menu_id, :date_prestation, :heure_livraison, :lieu_livraison, :ville_livraison, :distance_km, :frais_livraison, :nombre_personnes, :prix_total, :statut)'
    );
    $insert->execute([
        'utilisateur_id' => $utilisateurId,
        'menu_id' => $menuId,
        'date_prestation' => $date,
        'heure_livraison' => $heure,
        'lieu_livraison' => trim($lieu),
        'ville_livraison' => trim($ville),
        'distance_km' => number_format($delivery['distance_km'], 2, '.', ''),
        'frais_livraison' => number_format($delivery['fee'], 2, '.', ''),
        'nombre_personnes' => $personnes,
        'prix_total' => $prixTotal,
        'statut' => 'en attente',
    ]);
    $commandeId = (int) $pdo->lastInsertId();
    $update = $pdo->prepare('UPDATE menu SET stock = stock - 1 WHERE menu_id = :id AND stock > 0');
    $update->execute(['id' => $menuId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Stock modifié pendant la commande.');
    }
    $historique = $pdo->prepare('INSERT INTO suivi_commande (commande_id, ancien_statut, nouveau_statut) VALUES (:commande_id, NULL, :nouveau_statut)');
    $historique->execute(['commande_id' => $commandeId, 'nouveau_statut' => 'en attente']);
    $pdo->commit();
    require_once __DIR__ . '/../services/NoSqlStatistics.php';
    projectOrderToNoSql($pdo, $commandeId);
    try {
        require_once __DIR__ . '/../services/Mailer.php';
        sendApplicationMail(
            $compte['email'],
            'Confirmation de votre demande de commande',
            "Bonjour {$compte['prenom']},\n\nVotre demande de commande n°{$commandeId} a bien été enregistrée pour un montant de {$prixTotal} €. Son statut est « en attente ».\n"
        );
    } catch (Throwable $mailError) {
        error_log('Commande créée, mais e-mail de confirmation non envoyé : ' . $mailError->getMessage());
    }
    sendJsonResponse(['message' => 'Demande de commande enregistrée, en attente de validation.', 'commande_id' => $commandeId, 'prix_menu' => number_format($prixMenu, 2, '.', ''), 'reduction' => number_format($reduction, 2, '.', ''), 'distance_km' => number_format($delivery['distance_km'], 2, '.', ''), 'frais_livraison' => number_format($delivery['fee'], 2, '.', ''), 'prix_total' => $prixTotal, 'statut' => 'en attente'], 201);
} catch (DomainException $e) {
    sendJsonResponse(['erreur' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Échec de création de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
