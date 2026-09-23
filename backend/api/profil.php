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
        $stmt = $pdo->prepare('SELECT nom, prenom, email, telephone, adresse FROM utilisateur WHERE utilisateur_id = :id AND actif = 1 AND role = :role');
        $stmt->execute(['id' => $id, 'role' => 'utilisateur']);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            sendJsonResponse(['erreur' => 'Profil indisponible.'], 404);
            exit;
        }
        sendJsonResponse(['profil' => $profile]);
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
    $body = file_get_contents('php://input');
    if ($body === false || strlen($body) > 16384) {
        sendJsonResponse(['erreur' => 'Requête invalide ou trop volumineuse.'], 400);
        exit;
    }
    $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) {
        sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
        exit;
    }
    $limits = ['nom' => 100, 'prenom' => 100, 'email' => 150, 'telephone' => 20, 'adresse' => 255];
    $values = [];
    foreach ($limits as $field => $limit) {
        $value = $input[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > $limit) {
            sendJsonResponse(['erreur' => 'Informations personnelles invalides.'], 422);
            exit;
        }
        $values[$field] = trim($value);
    }
    $values['email'] = strtolower($values['email']);
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['erreur' => 'Adresse e-mail invalide.'], 422);
        exit;
    }
    $values['id'] = $id;
    $stmt = $pdo->prepare(
        'UPDATE utilisateur SET nom = :nom, prenom = :prenom, email = :email, telephone = :telephone, adresse = :adresse
         WHERE utilisateur_id = :id AND actif = 1 AND role = \'utilisateur\''
    );
    $stmt->execute($values);
    sendJsonResponse(['message' => 'Informations personnelles mises à jour.']);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        sendJsonResponse(['erreur' => 'Adresse e-mail déjà utilisée.'], 409);
    } else {
        error_log('Échec de mise à jour du profil : ' . $e->getMessage());
        sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
    }
} catch (Throwable $e) {
    error_log('Échec du profil : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
