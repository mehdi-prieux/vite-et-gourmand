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

try {
    require_once __DIR__ . '/../config/session.php';
    startSecureSession();
    $staffId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($staffId) || !in_array($_SESSION['role'] ?? null, ['employe', 'administrateur'], true)) {
        sendJsonResponse(['erreur' => 'Accès réservé au personnel.'], 403);
        exit;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403);
        exit;
    }
    $input = json_decode(file_get_contents('php://input') ?: '', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) {
        sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
        exit;
    }
    $action = $input['action'] ?? 'annuler';
    $commandeId = filter_var($input['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $mode = $input['mode_contact'] ?? null;
    $motif = is_string($input['motif'] ?? null) ? trim($input['motif']) : '';
    if (!in_array($action, ['modifier', 'annuler'], true) || $commandeId === false || $commandeId === null
        || !in_array($mode, ['email', 'telephone'], true) || strlen($motif) < 10 || strlen($motif) > 1000) {
        sendJsonResponse(['erreur' => 'Commande, action, mode de contact ou motif invalide.'], 422);
        exit;
    }

    require __DIR__ . '/../config/database.php';
    $pdo->beginTransaction();
    $staff = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = ? FOR UPDATE');
    $staff->execute([$staffId]);
    $account = $staff->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool) $account['actif'] || !in_array($account['role'], ['employe', 'administrateur'], true)) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403);
        exit;
    }
    $stmt = $pdo->prepare('SELECT c.menu_id, c.statut, m.nombre_personnes_min, m.prix FROM commande c INNER JOIN menu m ON m.menu_id = c.menu_id WHERE c.commande_id = ? FOR UPDATE');
    $stmt->execute([$commandeId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }
    if (in_array($order['statut'], ['annulée', 'terminée'], true)) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Cette commande ne peut plus être modifiée.'], 409);
        exit;
    }

    if ($action === 'annuler') {
        $pdo->prepare("UPDATE commande SET statut = 'annulée' WHERE commande_id = ?")->execute([$commandeId]);
        $pdo->prepare('INSERT INTO suivi_commande (commande_id, ancien_statut, nouveau_statut) VALUES (?, ?, ?)')->execute([$commandeId, $order['statut'], 'annulée']);
        $pdo->prepare("INSERT INTO intervention_commande (commande_id, employe_id, type_intervention, mode_contact, motif) VALUES (?, ?, 'annulation', ?, ?)")->execute([$commandeId, $staffId, $mode, $motif]);
        $pdo->prepare('UPDATE menu SET stock = stock + 1 WHERE menu_id = ?')->execute([$order['menu_id']]);
        $pdo->commit();
        require_once __DIR__ . '/../services/NoSqlStatistics.php';
        projectOrderToNoSql($pdo, $commandeId);
        sendJsonResponse(['message' => 'Commande annulée après contact client.', 'commande_id' => $commandeId, 'statut' => 'annulée']);
        exit;
    }

    $people = filter_var($input['nombre_personnes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    $date = is_string($input['date_prestation'] ?? null) ? $input['date_prestation'] : '';
    $time = is_string($input['heure_livraison'] ?? null) ? $input['heure_livraison'] : '';
    $address = is_string($input['lieu_livraison'] ?? null) ? trim($input['lieu_livraison']) : '';
    $city = is_string($input['ville_livraison'] ?? null) ? trim($input['ville_livraison']) : '';
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $dateValue !== false && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));
    if ($people === false || $people < (int) $order['nombre_personnes_min'] || !$validDate
        || $dateValue < new DateTimeImmutable('today') || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)
        || $address === '' || strlen($address) > 255 || $city === '' || strlen($city) > 100) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Nouvelles informations de commande invalides.'], 422);
        exit;
    }
    require_once __DIR__ . '/../services/DeliveryCalculator.php';
    $delivery = calculateDelivery($address, $city);
    $base = round((float) $order['prix'] * $people / (int) $order['nombre_personnes_min'], 2);
    $discount = $people >= (int) $order['nombre_personnes_min'] + 5 ? round($base * 0.10, 2) : 0.0;
    $total = round($base - $discount + $delivery['fee'], 2);
    $update = $pdo->prepare('UPDATE commande SET date_prestation=?, heure_livraison=?, lieu_livraison=?, ville_livraison=?, distance_km=?, frais_livraison=?, nombre_personnes=?, prix_total=? WHERE commande_id=?');
    $update->execute([$date, $time, $address, $city, $delivery['distance_km'], $delivery['fee'], $people, $total, $commandeId]);
    $pdo->prepare("INSERT INTO intervention_commande (commande_id, employe_id, type_intervention, mode_contact, motif) VALUES (?, ?, 'modification', ?, ?)")->execute([$commandeId, $staffId, $mode, $motif]);
    $pdo->commit();
    require_once __DIR__ . '/../services/NoSqlStatistics.php';
    projectOrderToNoSql($pdo, $commandeId);
    sendJsonResponse([
        'message' => 'Commande modifiée après contact client.',
        'commande_id' => $commandeId,
        'prix_total' => number_format($total, 2, '.', ''),
        'frais_livraison' => number_format($delivery['fee'], 2, '.', ''),
    ]);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendJsonResponse(['erreur' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Échec intervention commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
