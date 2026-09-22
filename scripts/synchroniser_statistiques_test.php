<?php

declare(strict_types=1);

// Reprise idempotente des commandes vers la destination locale explicitement autorisée.
// Par défaut, aucun document n'est écrit. Exécuter avec --apply après démarrage de CouchDB.
if (PHP_SAPI !== 'cli' || !in_array($argv[1] ?? '', ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage : php scripts/synchroniser_statistiques_test.php --dry-run|--apply\n");
    exit(2);
}
if (getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'vite_gourmand_test'
    || getenv('COUCHDB_URL') !== 'http://127.0.0.1:5984'
    || getenv('COUCHDB_DATABASE') !== 'vite_gourmand_stats_test') {
    fwrite(STDERR, "STOP : seules vite_gourmand_test et la destination CouchDB locale approuvée sont autorisées.\n");
    exit(42);
}

require __DIR__ . '/../backend/config/database.php';
$actualDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDatabase !== 'vite_gourmand_test') {
    fwrite(STDERR, "STOP : base SQL réellement utilisée : " . var_export($actualDatabase, true) . "\n");
    exit(42);
}

$orders = $pdo->query('SELECT commande_id FROM commande ORDER BY commande_id')->fetchAll(PDO::FETCH_COLUMN);
printf("MySQL=%s ; destination CouchDB=%s/%s ; commandes=%d ; mode=%s\n",
    $actualDatabase, getenv('COUCHDB_URL'), getenv('COUCHDB_DATABASE'), count($orders), $argv[1]);
if ($argv[1] === '--dry-run') exit(0);

require __DIR__ . '/../backend/services/NoSqlStatistics.php';
$statistics = new NoSqlStatistics();
foreach ($orders as $id) {
    $statistics->upsertOrder($pdo, (int) $id);
}
$expected = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(prix_total), 0) AS revenue FROM commande WHERE statut <> 'annulée'")->fetch(PDO::FETCH_ASSOC);
$actual = $statistics->statistics(null, null, null);
if ((int) $expected['total'] !== $actual['nombre_commandes']
    || abs((float) $expected['revenue'] - (float) $actual['chiffre_affaires']) > 0.01) {
    fwrite(STDERR, "ÉCHEC : le nombre de commandes ou le chiffre d'affaires CouchDB ne correspond pas à MySQL.\n");
    exit(1);
}
printf("PASS : %d commandes actives et %s EUR vérifiés depuis CouchDB.\n",
    $actual['nombre_commandes'], $actual['chiffre_affaires']);
