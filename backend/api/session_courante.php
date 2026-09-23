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

    require_once __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare('SELECT utilisateur_id, nom, prenom, email, role, actif FROM utilisateur WHERE utilisateur_id = :id LIMIT 1');
    $stmt->execute(['id' => $utilisateurId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !(bool) $user['actif'] || !in_array($user['role'], ['utilisateur', 'employe', 'administrateur'], true)) {
        $_SESSION = [];
        session_regenerate_id(true);
        sendJsonResponse(['erreur' => 'Session indisponible. Veuillez vous reconnecter.'], 401);
        exit;
    }

    // Le rôle enregistré en session ne doit pas survivre à une modification en base.
    $_SESSION['role'] = $user['role'];
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    sendJsonResponse([
        'utilisateur' => [
            'utilisateur_id' => (int) $user['utilisateur_id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    error_log('Échec de consultation de session : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
