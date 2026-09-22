<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405); exit; }
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) { sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415); exit; }
try {
    $input = json_decode(file_get_contents('php://input') ?: '', true, 8, JSON_THROW_ON_ERROR);
    $token = is_string($input['token'] ?? null) ? $input['token'] : '';
    $password = $input['mot_de_passe'] ?? null;
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)
        || !is_string($password) || strlen($password) < 12 || strlen($password) > 72
        || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        sendJsonResponse(['erreur' => 'Lien ou nouveau mot de passe invalide.'], 422); exit;
    }
    require __DIR__ . '/../config/database.php';
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT r.reinitialisation_id, r.utilisateur_id
         FROM reinitialisation_mot_de_passe r INNER JOIN utilisateur u ON u.utilisateur_id = r.utilisateur_id
         WHERE r.token_hash = :hash AND r.utilise_le IS NULL AND r.expire_le > NOW() AND u.actif = 1 FOR UPDATE'
    );
    $stmt->execute(['hash' => hash('sha256', $token)]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reset) {
        $pdo->rollBack(); sendJsonResponse(['erreur' => 'Ce lien est invalide ou expiré.'], 410); exit;
    }
    $pdo->prepare('UPDATE utilisateur SET mot_de_passe = :password WHERE utilisateur_id = :id')->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['utilisateur_id']]);
    $pdo->prepare('UPDATE reinitialisation_mot_de_passe SET utilise_le = NOW() WHERE reinitialisation_id = :id')->execute(['id' => $reset['reinitialisation_id']]);
    $pdo->commit();
    sendJsonResponse(['message' => 'Mot de passe mis à jour. Vous pouvez vous connecter.']);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Échec de réinitialisation du mot de passe : ' . $e->getMessage()); sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
