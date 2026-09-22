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

$email = $input['email'] ?? null;
$password = $input['mot_de_passe'] ?? null;
if (!is_string($email) || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !is_string($password) || $password === '' || strlen($password) > 4096) {
    sendJsonResponse(['erreur' => 'Identifiants invalides.'], 401);
    exit;
}

try {
    require __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare('SELECT utilisateur_id, nom, prenom, email, mot_de_passe, role, actif FROM utilisateur WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !(bool) $user['actif'] || !password_verify($password, $user['mot_de_passe'])) {
        sendJsonResponse(['erreur' => 'Identifiants invalides.'], 401);
        exit;
    }

    require_once __DIR__ . '/../config/session.php';
    startSecureSession();
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Impossible de renouveler la session.');
    }
    $_SESSION['utilisateur_id'] = (int) $user['utilisateur_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    sendJsonResponse([
        'message' => 'Connexion réussie.',
        'utilisateur' => [
            'utilisateur_id' => (int) $user['utilisateur_id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    error_log('Échec de connexion utilisateur : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
