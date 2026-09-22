<?php

require_once __DIR__ . '/_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['error' => 'Méthode non autorisée.'], 405);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../controllers/PlatController.php';

    $controller = new PlatController($pdo);
    sendJsonResponse($controller->index());
} catch (Throwable $e) {
    error_log('Erreur API plats : ' . $e->getMessage());
    sendJsonResponse(['error' => 'Erreur interne du serveur.'], 500);
}
