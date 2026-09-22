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
$commandeId = filter_var($input['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($commandeId === false || $commandeId === null) {
    sendJsonResponse(['erreur' => 'Identifiant de commande invalide.'], 422);
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
    $stmt = $pdo->prepare('SELECT menu_id, statut FROM commande WHERE commande_id = :commande_id AND utilisateur_id = :utilisateur_id FOR UPDATE');
    $stmt->execute(['commande_id' => $commandeId, 'utilisateur_id' => $utilisateurId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$commande) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }
    if ($commande['statut'] !== 'en attente') {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Seule une commande en attente peut être annulée.'], 409);
        exit;
    }
    // L'annulation supprime la demande non acceptée : le schéma ne possède pas
    // de statut « annulée ». L'opération et la restitution du stock sont atomiques.
    $delete = $pdo->prepare('DELETE FROM commande WHERE commande_id = :id AND utilisateur_id = :utilisateur_id AND statut = :statut');
    $delete->execute(['id' => $commandeId, 'utilisateur_id' => $utilisateurId, 'statut' => 'en attente']);
    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('La commande a changé pendant son annulation.');
    }
    $restore = $pdo->prepare('UPDATE menu SET stock = stock + 1 WHERE menu_id = :id');
    $restore->execute(['id' => $commande['menu_id']]);
    if ($restore->rowCount() !== 1) {
        throw new RuntimeException('Impossible de restituer le stock.');
    }
    $pdo->commit();
    sendJsonResponse(['message' => 'Commande en attente annulée. Le stock a été restitué.', 'commande_id' => $commandeId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Échec de l’annulation de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
