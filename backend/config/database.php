<?php

require_once __DIR__ . '/env.php';

// Configuration fournie par l'environnement du serveur (voir .env.example).
// Les valeurs par défaut sont limitées au développement local.
$dbHost = appConfig('DB_HOST') ?: 'localhost';
$dbPort = appConfig('DB_PORT') ?: '3306';
$dbName = appConfig('DB_NAME') ?: 'vite_gourmand';
$dbUsername = appConfig('DB_USER') ?: 'root';
$dbPassword = appConfig('DB_PASSWORD');
$dbPassword = $dbPassword === false ? '' : $dbPassword;

if (appConfig('APP_ENV') === 'test' && $dbName !== 'vite_gourmand_test') {
    throw new RuntimeException('En test, seule la base vite_gourmand_test est autorisée.');
}
if (appConfig('APP_ENV') === 'production'
    && (appConfig('DB_NAME') === false || appConfig('DB_USER') === false || appConfig('DB_PASSWORD') === false)) {
    throw new RuntimeException('La configuration MySQL explicite est requise en production.');
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Journalisation côté serveur uniquement : aucun identifiant n'est exposé au client.
    error_log('Échec de connexion à la base de données : ' . $e->getMessage());
    throw new RuntimeException('Connexion à la base de données indisponible.', 0, $e);
}
