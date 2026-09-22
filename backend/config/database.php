<?php

// Configuration fournie par l'environnement du serveur (voir .env.example).
// Les valeurs par défaut sont limitées au développement local.
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'vite_gourmand';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD');
$password = $password === false ? '' : $password;

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Le détail de l'erreur (hôte, identifiants, etc.) ne doit pas être renvoyé au client.
    error_log('Échec de connexion à la base de données : ' . $e->getMessage());
    http_response_code(500);
    exit('Erreur interne de connexion à la base de données.');
}
