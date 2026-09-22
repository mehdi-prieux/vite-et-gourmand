<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if (!is_array($input)) {
    sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
    exit;
}

$fields = ['nom' => 100, 'prenom' => 100, 'email' => 150];
$values = [];
foreach ($fields as $field => $maxLength) {
    if (!isset($input[$field]) || !is_string($input[$field])) {
        sendJsonResponse(['erreur' => 'Champs obligatoires invalides.'], 422);
        exit;
    }
    $value = trim($input[$field]);
    if ($value === '' || strlen($value) > $maxLength) {
        sendJsonResponse(['erreur' => 'Champs obligatoires invalides.'], 422);
        exit;
    }
    $values[$field] = $value;
}

$values['email'] = strtolower($values['email']);
if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(['erreur' => 'Adresse e-mail invalide.'], 422);
    exit;
}

$password = $input['mot_de_passe'] ?? null;
if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    sendJsonResponse(['erreur' => 'Le mot de passe doit contenir entre 12 et 72 octets.'], 422);
    exit;
}

foreach (['telephone' => 20, 'adresse' => 255] as $field => $maxLength) {
    $value = $input[$field] ?? null;
    if ($value !== null && (!is_string($value) || strlen(trim($value)) > $maxLength)) {
        sendJsonResponse(['erreur' => 'Coordonnées invalides.'], 422);
        exit;
    }
    $values[$field] = $value === null ? null : trim($value);
}

try {
    require __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare('INSERT INTO utilisateur (nom, prenom, email, telephone, adresse, mot_de_passe, role) VALUES (:nom, :prenom, :email, :telephone, :adresse, :mot_de_passe, :role)');
    $stmt->execute([
        'nom' => $values['nom'],
        'prenom' => $values['prenom'],
        'email' => $values['email'],
        'telephone' => $values['telephone'],
        'adresse' => $values['adresse'],
        'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'utilisateur',
    ]);
    sendJsonResponse(['message' => 'Compte créé.', 'utilisateur_id' => (int) $pdo->lastInsertId()], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        sendJsonResponse(['erreur' => 'Adresse e-mail déjà utilisée.'], 409);
    } else {
        error_log('Échec de création du compte : ' . $e->getMessage());
        sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
    }
}
