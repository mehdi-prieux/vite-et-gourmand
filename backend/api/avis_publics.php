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
        'SELECT a.avis_id, a.note, a.commentaire, u.prenom, u.nom
         FROM avis AS a
         INNER JOIN utilisateur AS u ON u.utilisateur_id = a.utilisateur_id
         WHERE a.valide = 1
         ORDER BY a.avis_id DESC LIMIT 12'
    );
    sendJsonResponse(['avis' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des avis publics : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
