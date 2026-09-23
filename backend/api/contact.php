<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/_response.php';

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
if ($body === false || strlen($body) > 20000) {
    sendJsonResponse(['erreur' => 'Requête invalide ou trop volumineuse.'], 400);
    exit;
}
try {
    $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
    exit;
}
if (!is_array($input) || array_is_list($input)) {
    sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
    exit;
}
$email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
$title = is_string($input['titre'] ?? null) ? trim($input['titre']) : '';
$description = is_string($input['description'] ?? null) ? trim($input['description']) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
    || strlen($title) < 3 || strlen($title) > 150
    || strlen($description) < 10 || strlen($description) > 5000) {
    sendJsonResponse(['erreur' => 'Adresse e-mail, titre ou description invalide.'], 422);
    exit;
}

try {
    require_once __DIR__ . '/../services/Mailer.php';
    $recipient = appConfig('CONTACT_EMAIL') ?: '';
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['erreur' => 'Le service de contact n’est pas configuré.'], 503);
        exit;
    }
    $message = "Nouvelle demande depuis le site Vite & Gourmand\n\n"
        . "Adresse de réponse : {$email}\n"
        . "Titre : {$title}\n\n"
        . $description;
    if (!sendApplicationMail($recipient, '[Contact] ' . $title, $message)) {
        throw new RuntimeException('Échec du transport de courrier.');
    }
    sendJsonResponse(['message' => 'Votre demande a bien été envoyée.'], 202);
} catch (Throwable $e) {
    error_log('Échec du formulaire de contact : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Le message n’a pas pu être envoyé.'], 500);
}
