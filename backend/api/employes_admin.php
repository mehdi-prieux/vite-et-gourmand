<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) { header('Allow: GET, POST'); sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405); exit; }

try {
    require_once __DIR__ . '/../config/session.php'; startSecureSession();
    $adminId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($adminId) || $adminId < 1 || ($_SESSION['role'] ?? null) !== 'administrateur') { sendJsonResponse(['erreur' => 'Accès administrateur requis.'], 403); exit; }
    require __DIR__ . '/../config/database.php';
    $check = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id'); $check->execute(['id' => $adminId]); $admin = $check->fetch(PDO::FETCH_ASSOC);
    if (!$admin || !(bool) $admin['actif'] || $admin['role'] !== 'administrateur') { sendJsonResponse(['erreur' => 'Accès refusé.'], 403); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT utilisateur_id, nom, prenom, email, telephone, actif FROM utilisateur WHERE role = 'employe' ORDER BY nom, prenom, utilisateur_id");
        sendJsonResponse(['employes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) { sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415); exit; }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403); exit; }
    $input = json_decode(file_get_contents('php://input') ?: '', true, 16, JSON_THROW_ON_ERROR);
    $action = $input['action'] ?? null;
    if ($action === 'creer') {
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $password = $input['mot_de_passe'] ?? null;
        $nom = is_string($input['nom'] ?? null) ? trim($input['nom']) : '';
        $prenom = is_string($input['prenom'] ?? null) ? trim($input['prenom']) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || $nom === '' || strlen($nom) > 100 || $prenom === '' || strlen($prenom) > 100
            || !is_string($password) || strlen($password) < 12 || strlen($password) > 72 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            sendJsonResponse(['erreur' => 'Informations employé ou mot de passe invalide.'], 422); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, actif) VALUES (:nom, :prenom, :email, :password, 'employe', 1)");
        $stmt->execute(['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
        $employeeId = (int) $pdo->lastInsertId();
        try {
            require_once __DIR__ . '/../services/Mailer.php';
            sendApplicationMail($email, 'Création de votre compte employé', "Bonjour {$prenom},\n\nUn compte employé Vite & Gourmand a été créé pour vous. Pour des raisons de sécurité, le mot de passe ne figure pas dans cet e-mail : rapprochez-vous de l’administrateur.\n");
        } catch (Throwable $mailError) { error_log('Employé créé, mais e-mail non envoyé : ' . $mailError->getMessage()); }
        sendJsonResponse(['message' => 'Compte employé créé.', 'utilisateur_id' => $employeeId], 201); exit;
    }
    if ($action === 'activer') {
        $employeeId = filter_var($input['utilisateur_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actif = filter_var($input['actif'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($employeeId === false || $actif === null) { sendJsonResponse(['erreur' => 'Employé ou état invalide.'], 422); exit; }
        $stmt = $pdo->prepare("UPDATE utilisateur SET actif = :actif WHERE utilisateur_id = :id AND role = 'employe'");
        $stmt->execute(['actif' => $actif ? 1 : 0, 'id' => $employeeId]);
        if ($stmt->rowCount() !== 1) { sendJsonResponse(['erreur' => 'Employé introuvable ou déjà dans cet état.'], 404); exit; }
        sendJsonResponse(['message' => $actif ? 'Compte employé activé.' : 'Compte employé désactivé.']); exit;
    }
    sendJsonResponse(['erreur' => 'Action inconnue.'], 422);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') sendJsonResponse(['erreur' => 'Adresse e-mail déjà utilisée.'], 409);
    else { error_log('Échec de gestion employé : ' . $e->getMessage()); sendJsonResponse(['erreur' => 'Erreur interne.'], 500); }
} catch (Throwable $e) {
    error_log('Échec de gestion employé : ' . $e->getMessage()); sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
