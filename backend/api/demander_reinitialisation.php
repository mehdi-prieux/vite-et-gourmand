<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405); exit;
}
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415); exit;
}
try {
    $input = json_decode(file_get_contents('php://input') ?: '', true, 8, JSON_THROW_ON_ERROR);
    $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        sendJsonResponse(['erreur' => 'Adresse e-mail invalide.'], 422); exit;
    }
    require __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare('SELECT utilisateur_id, prenom FROM utilisateur WHERE email = :email AND actif = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE reinitialisation_mot_de_passe SET utilise_le = NOW() WHERE utilisateur_id = :id AND utilise_le IS NULL')->execute(['id' => $user['utilisateur_id']]);
        $pdo->prepare('INSERT INTO reinitialisation_mot_de_passe (utilisateur_id, token_hash, expire_le) VALUES (:id, :hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute(['id' => $user['utilisateur_id'], 'hash' => $hash]);
        $pdo->commit();
        try {
            require_once __DIR__ . '/../services/Mailer.php';
            $base = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost:8000/frontend', '/');
            $link = $base . '/reinitialiser-mot-de-passe.html?token=' . rawurlencode($rawToken);
            sendApplicationMail($email, 'Réinitialisation de votre mot de passe', "Bonjour {$user['prenom']},\n\nCe lien est valable une heure et ne peut être utilisé qu’une fois :\n{$link}\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n");
        } catch (Throwable $mailError) {
            error_log('Jeton créé, mais e-mail de réinitialisation non envoyé : ' . $mailError->getMessage());
        }
    }
    // Réponse identique afin de ne pas révéler l'existence d'un compte.
    sendJsonResponse(['message' => 'Si un compte actif correspond à cette adresse, un lien de réinitialisation vient d’être envoyé.'], 202);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Échec de demande de réinitialisation : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
