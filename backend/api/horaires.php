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
    $stmt = $pdo->query(
        "SELECT horaire_id, jour, TIME_FORMAT(heure_ouverture, '%H:%i') AS heure_ouverture,
                TIME_FORMAT(heure_fermeture, '%H:%i') AS heure_fermeture
         FROM horaire ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'), horaire_id"
    );
    sendJsonResponse(['horaires' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des horaires : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
