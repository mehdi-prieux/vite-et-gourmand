<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

$commandeId = filter_var($_GET['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($commandeId === false || $commandeId === null) {
    sendJsonResponse(['erreur' => 'Identifiant de commande invalide.'], 422);
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

    require_once __DIR__ . '/../config/database.php';
    $accountStmt = $pdo->prepare('SELECT role, actif FROM utilisateur WHERE utilisateur_id = :id LIMIT 1');
    $accountStmt->execute(['id' => $id]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool) $account['actif'] || $account['role'] !== ($_SESSION['role'] ?? null)) {
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403);
        exit;
    }

    $orderStmt = $pdo->prepare(
        'SELECT c.commande_id, c.utilisateur_id, c.menu_id, m.titre AS menu_titre,
                c.date_prestation, c.heure_livraison, c.lieu_livraison, c.ville_livraison,
                c.distance_km, c.frais_livraison,
                c.nombre_personnes, c.statut, c.prix_total
         FROM commande AS c
         INNER JOIN menu AS m ON m.menu_id = c.menu_id
         WHERE c.commande_id = :id LIMIT 1'
    );
    $orderStmt->execute(['id' => $commandeId]);
    $commande = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$commande) {
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }
    $personnel = in_array($account['role'], ['employe', 'administrateur'], true);
    if (!$personnel && ($account['role'] !== 'utilisateur' || (int) $commande['utilisateur_id'] !== $id)) {
        // Ne pas révéler l'existence d'une commande appartenant à un autre client.
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }

    unset($commande['utilisateur_id']);
    $historyStmt = $pdo->prepare(
        'SELECT suivi_id, ancien_statut, nouveau_statut, date_modification
         FROM suivi_commande WHERE commande_id = :id
         ORDER BY date_modification ASC, suivi_id ASC'
    );
    $historyStmt->execute(['id' => $commandeId]);
    sendJsonResponse(['commande' => $commande, 'historique' => $historyStmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation du suivi de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
