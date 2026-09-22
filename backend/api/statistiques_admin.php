<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}

$start = $_GET['date_debut'] ?? '';
$end = $_GET['date_fin'] ?? '';
$menuId = $_GET['menu_id'] ?? '';
foreach ([$start, $end] as $date) {
    if ($date !== '' && (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))) {
        sendJsonResponse(['erreur' => 'Période invalide.'], 422);
        exit;
    }
}
$menu = $menuId === '' ? null : filter_var($menuId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($menuId !== '' && ($menu === false || $menu === null)) {
    sendJsonResponse(['erreur' => 'Menu invalide.'], 422);
    exit;
}

try {
    require_once __DIR__ . '/../config/session.php';
    startSecureSession();
    $adminId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($adminId) || ($_SESSION['role'] ?? null) !== 'administrateur') {
        sendJsonResponse(['erreur' => 'Accès administrateur requis.'], 403);
        exit;
    }
    require __DIR__ . '/../config/database.php';
    $account = $pdo->prepare("SELECT 1 FROM utilisateur WHERE utilisateur_id = ? AND role = 'administrateur' AND actif = 1");
    $account->execute([$adminId]);
    if (!$account->fetchColumn()) {
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403);
        exit;
    }
    if (appConfig('APP_ENV') !== 'test'
        || appConfig('COUCHDB_URL') !== 'http://127.0.0.1:5984'
        || appConfig('COUCHDB_DATABASE') !== 'vite_gourmand_stats_test'
        || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'vite_gourmand_test') {
        sendJsonResponse(['erreur' => 'Statistiques NoSQL indisponibles.'], 503);
        exit;
    }
    require_once __DIR__ . '/../services/NoSqlStatistics.php';
    sendJsonResponse((new NoSqlStatistics())->statistics($start ?: null, $end ?: null, $menu));
} catch (Throwable $e) {
    error_log('Échec statistiques administrateur : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Statistiques NoSQL indisponibles.'], 503);
}
