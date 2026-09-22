<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

try {
    require_once __DIR__ . '/../config/session.php';
    startSecureSession();

    $utilisateurId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($utilisateurId) || $utilisateurId < 1) {
        sendJsonResponse(['erreur' => 'Authentification requise.'], 401);
        exit;
    }

    if (($_SESSION['role'] ?? null) !== 'utilisateur') {
        sendJsonResponse(['erreur' => 'Accès réservé aux clients.'], 403);
        exit;
    }

    require_once __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare(
        'SELECT c.commande_id, c.menu_id, m.titre AS menu_titre,
                c.date_prestation, c.heure_livraison, c.lieu_livraison,
                c.nombre_personnes, c.statut, c.prix_total
         FROM commande AS c
         INNER JOIN menu AS m ON m.menu_id = c.menu_id
         WHERE c.utilisateur_id = :utilisateur_id
         ORDER BY c.commande_id DESC'
    );
    $stmt->execute(['utilisateur_id' => $utilisateurId]);
    sendJsonResponse(['commandes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des commandes client : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
