<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

$statuts = [
    'en attente', 'accepté', 'en préparation', 'en cours de livraison',
    'livré', 'en attente du retour matériel', 'terminée', 'annulée',
];
$statut = $_GET['statut'] ?? null;
$client = $_GET['client'] ?? null;
if (($statut !== null && (!is_string($statut) || !in_array($statut, $statuts, true)))
    || ($client !== null && (!is_string($client) || strlen(trim($client)) > 150))) {
    sendJsonResponse(['erreur' => 'Filtres invalides.'], 422);
    exit;
}
$client = $client === null ? null : trim($client);

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

    $sql = 'SELECT c.commande_id, c.date_prestation, c.heure_livraison, c.lieu_livraison, c.ville_livraison,
                   c.distance_km, c.frais_livraison,
                   c.nombre_personnes, c.statut, c.prix_total,
                   m.menu_id, m.titre AS menu_titre,
                   u.utilisateur_id, u.nom AS client_nom, u.prenom AS client_prenom,
                   u.email AS client_email, u.telephone AS client_telephone
            FROM commande AS c
            INNER JOIN menu AS m ON m.menu_id = c.menu_id
            INNER JOIN utilisateur AS u ON u.utilisateur_id = c.utilisateur_id';
    $conditions = [];
    $params = [];
    if ($statut !== null) {
        $conditions[] = 'c.statut = :statut';
        $params['statut'] = $statut;
    }
    if ($client !== null && $client !== '') {
        // Une recherche partielle sur le nom, le prénom ou l'adresse e-mail.
        // Échapper % et _ pour que la saisie reste du texte et non un motif SQL.
        $conditions[] = "(u.nom LIKE :client_nom OR u.prenom LIKE :client_prenom OR u.email LIKE :client_email)";
        $search = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $client) . '%';
        $params['client_nom'] = $search;
        $params['client_prenom'] = $search;
        $params['client_email'] = $search;
        $conditions[count($conditions) - 1] = "(u.nom LIKE :client_nom ESCAPE '!' OR u.prenom LIKE :client_prenom ESCAPE '!' OR u.email LIKE :client_email ESCAPE '!')";
    }
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY c.date_prestation ASC, c.commande_id ASC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    sendJsonResponse(['commandes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    error_log('Échec de consultation des commandes du personnel : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
