<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

try {
    require_once __DIR__ . '/../config/session.php';
    startSecureSession();
    $id = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($id) || $id < 1 || ($_SESSION['role'] ?? null) !== 'utilisateur') {
        sendJsonResponse(['erreur' => 'Authentification client requise.'], 401);
        exit;
    }
    require __DIR__ . '/../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare('SELECT avis_id, commande_id, note, commentaire, statut FROM avis WHERE utilisateur_id = :id ORDER BY avis_id DESC');
        $stmt->execute(['id' => $id]);
        sendJsonResponse(['avis' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415);
        exit;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403);
        exit;
    }
    $input = json_decode(file_get_contents('php://input') ?: '', true, 16, JSON_THROW_ON_ERROR);
    $commandeId = filter_var($input['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $note = filter_var($input['note'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $commentaire = is_string($input['commentaire'] ?? null) ? trim($input['commentaire']) : '';
    if ($commandeId === false || $commandeId === null || $note === false || $note === null || strlen($commentaire) < 3 || strlen($commentaire) > 2000) {
        sendJsonResponse(['erreur' => 'Commande, note ou commentaire invalide.'], 422);
        exit;
    }
    $pdo->beginTransaction();
    $order = $pdo->prepare('SELECT statut FROM commande WHERE commande_id = :commande AND utilisateur_id = :utilisateur FOR UPDATE');
    $order->execute(['commande' => $commandeId, 'utilisateur' => $id]);
    if ($order->fetchColumn() !== 'terminée') {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Un avis est possible uniquement après une commande terminée.'], 409);
        exit;
    }
    $existing = $pdo->prepare('SELECT avis_id FROM avis WHERE commande_id = :commande AND utilisateur_id = :utilisateur LIMIT 1');
    $existing->execute(['commande' => $commandeId, 'utilisateur' => $id]);
    if ($existing->fetchColumn() !== false) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Un avis existe déjà pour cette commande.'], 409);
        exit;
    }
    $insert = $pdo->prepare('INSERT INTO avis (utilisateur_id, commande_id, note, commentaire, statut, valide) VALUES (:utilisateur, :commande, :note, :commentaire, \'en attente\', 0)');
    $insert->execute(['utilisateur' => $id, 'commande' => $commandeId, 'note' => $note, 'commentaire' => $commentaire]);
    $avisId = (int) $pdo->lastInsertId();
    $pdo->commit();
    sendJsonResponse(['message' => 'Avis envoyé pour modération.', 'avis_id' => $avisId], 201);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Échec de gestion de l’avis client : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
