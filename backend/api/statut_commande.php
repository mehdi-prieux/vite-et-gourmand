<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405);
    exit;
}
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415);
    exit;
}
$body = file_get_contents('php://input');
if ($body === false || strlen($body) > 16384) {
    sendJsonResponse(['erreur' => 'Requête invalide ou trop volumineuse.'], 400);
    exit;
}
try {
    $input = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
    exit;
}
if (!is_array($input) || array_is_list($input)) {
    sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400);
    exit;
}
$commandeId = filter_var($input['commande_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nouveauStatut = $input['statut'] ?? null;
$transitions = [
    'en attente' => ['accepté'],
    'accepté' => ['en préparation'],
    'en préparation' => ['en cours de livraison'],
    'en cours de livraison' => ['livré'],
    'livré' => ['en attente du retour matériel', 'terminée'],
    'en attente du retour matériel' => ['terminée'],
    'terminée' => [],
];
if ($commandeId === false || !is_string($nouveauStatut) || !array_key_exists($nouveauStatut, $transitions)) {
    sendJsonResponse(['erreur' => 'Commande ou statut invalide.'], 422);
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
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403);
        exit;
    }
    require_once __DIR__ . '/../config/database.php';
    $pdo->beginTransaction();
    $staff = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id FOR UPDATE');
    $staff->execute(['id' => $id]);
    $account = $staff->fetch(PDO::FETCH_ASSOC);
    if (!$account || !(bool) $account['actif'] || !in_array($account['role'], ['employe', 'administrateur'], true)) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT c.statut, u.email, u.prenom
         FROM commande AS c INNER JOIN utilisateur AS u ON u.utilisateur_id = c.utilisateur_id
         WHERE c.commande_id = :id FOR UPDATE'
    );
    $stmt->execute(['id' => $commandeId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$commande) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Commande introuvable.'], 404);
        exit;
    }
    $ancienStatut = $commande['statut'];
    if (!in_array($nouveauStatut, $transitions[$ancienStatut] ?? [], true)) {
        $pdo->rollBack();
        sendJsonResponse(['erreur' => 'Transition de statut non autorisée.'], 409);
        exit;
    }
    $update = $pdo->prepare('UPDATE commande SET statut = :statut WHERE commande_id = :id');
    $update->execute(['statut' => $nouveauStatut, 'id' => $commandeId]);
    $history = $pdo->prepare('INSERT INTO suivi_commande (commande_id, ancien_statut, nouveau_statut) VALUES (:commande_id, :ancien_statut, :nouveau_statut)');
    $history->execute(['commande_id' => $commandeId, 'ancien_statut' => $ancienStatut, 'nouveau_statut' => $nouveauStatut]);
    $pdo->commit();
    require_once __DIR__ . '/../services/NoSqlStatistics.php';
    projectOrderToNoSql($pdo, $commandeId);
    if (in_array($nouveauStatut, ['en attente du retour matériel', 'terminée'], true)) {
        try {
            require_once __DIR__ . '/../services/Mailer.php';
            $subject = $nouveauStatut === 'terminée'
                ? 'Votre commande est terminée'
                : 'Retour du matériel de votre commande';
            $message = $nouveauStatut === 'terminée'
                ? "Bonjour {$commande['prenom']},\n\nVotre commande n°{$commandeId} est terminée. Vous pouvez maintenant déposer un avis depuis votre espace client.\n"
                : "Bonjour {$commande['prenom']},\n\nVotre commande n°{$commandeId} attend le retour du matériel. Merci de contacter Vite & Gourmand et de restituer le matériel sous 10 jours ouvrés. Au-delà, des frais de 600 € peuvent être appliqués conformément aux CGV.\n";
            sendApplicationMail($commande['email'], $subject, $message);
        } catch (Throwable $mailError) {
            error_log('Statut modifié, mais e-mail non envoyé : ' . $mailError->getMessage());
        }
    }
    sendJsonResponse(['message' => 'Statut mis à jour.', 'commande_id' => $commandeId, 'ancien_statut' => $ancienStatut, 'nouveau_statut' => $nouveauStatut]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Échec de modification du statut de commande : ' . $e->getMessage());
    sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
