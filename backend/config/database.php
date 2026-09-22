<?php

// Configuration fournie par l'environnement du serveur (voir .env.example).
// Les valeurs par défaut sont limitées au développement local.
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'vite_gourmand';
$dbUsername = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD');
$dbPassword = $dbPassword === false ? '' : $dbPassword;

if (getenv('APP_ENV') === 'test' && $dbName !== 'vite_gourmand_test') {
    throw new RuntimeException('En test, seule la base vite_gourmand_test est autorisée.');
}
if (getenv('APP_ENV') === 'production'
    && (getenv('DB_NAME') === false || getenv('DB_USER') === false || getenv('DB_PASSWORD') === false)) {
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
