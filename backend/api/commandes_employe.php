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
    $id = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        sendJsonResponse(['erreur' => 'Authentification requise.'], 401);
        exit;
    }
    if (!in_array($_SESSION['role'] ?? null, ['employe', 'administrateur'], true)) {
        sendJsonResponse(['erreur' => 'Accès réservé au personnel.'], 403);
        exit;
    }

    require_once __DIR__ . '/../config/database.php';
    $staff = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id LIMIT 1');
    $staff->execute(['id' => $id]);
    $account = $staff->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool) $account['actif'] || !in_array($account['role'], ['employe', 'administrateur'], true)) {
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403);
        exit;
    }

    $stmt = $pdo->query(
        'SELECT c.commande_id, c.date_prestation, c.heure_livraison, c.lieu_livraison,
                c.nombre_personnes, c.statut, c.prix_total,
                m.menu_id, m.titre AS menu_titre,
                u.utilisateur_id, u.nom AS client_nom, u.prenom AS client_prenom,
                u.email AS client_email, u.telephone AS client_telephone
         FROM commande AS c
         INNER JOIN menu AS m ON m.menu_id = c.menu_id
         INNER JOIN utilisateur AS u ON u.utilisateur_id = c.utilisateur_id
         ORDER BY c.date_prestation ASC, c.commande_id ASC
         LIMIT 200'
    );
    sendJsonResponse(['commandes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des commandes du personnel : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
