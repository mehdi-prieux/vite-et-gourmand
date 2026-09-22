<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

try {
    require __DIR__ . '/../config/database.php';
    $stmt = $pdo->query('SELECT allergene_id, nom FROM allergene ORDER BY nom');
    sendJsonResponse(['allergenes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des allergènes : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
