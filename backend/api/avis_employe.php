<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405); exit;
}

try {
    require_once __DIR__ . '/../config/session.php'; startSecureSession();
    $id = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($id) || $id < 1 || !in_array($_SESSION['role'] ?? null, ['employe', 'administrateur'], true)) {
        sendJsonResponse(['erreur' => 'Accès réservé au personnel.'], 403); exit;
    }
    require __DIR__ . '/../config/database.php';
    $account = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id'); $account->execute(['id' => $id]);
    $staff = $account->fetch(PDO::FETCH_ASSOC);
    if (!$staff || !(bool) $staff['actif'] || !in_array($staff['role'], ['employe', 'administrateur'], true)) {
        sendJsonResponse(['erreur' => 'Accès refusé.'], 403); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query('SELECT a.avis_id, a.commande_id, a.note, a.commentaire, a.statut, u.nom, u.prenom FROM avis a INNER JOIN utilisateur u ON u.utilisateur_id = a.utilisateur_id ORDER BY a.avis_id DESC LIMIT 200');
        sendJsonResponse(['avis' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) { sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415); exit; }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403); exit; }
    $input = json_decode(file_get_contents('php://input') ?: '', true, 16, JSON_THROW_ON_ERROR);
    $avisId = filter_var($input['avis_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $statut = $input['statut'] ?? null;
    if ($avisId === false || !in_array($statut, ['validé', 'refusé'], true)) { sendJsonResponse(['erreur' => 'Avis ou décision invalide.'], 422); exit; }
    $stmt = $pdo->prepare('UPDATE avis SET statut = :statut, valide = :valide WHERE avis_id = :id');
    $stmt->execute(['statut' => $statut, 'valide' => $statut === 'validé' ? 1 : 0, 'id' => $avisId]);
    if ($stmt->rowCount() !== 1) { sendJsonResponse(['erreur' => 'Avis introuvable ou déjà traité.'], 404); exit; }
    sendJsonResponse(['message' => 'Avis ' . $statut . '.', 'avis_id' => $avisId, 'statut' => $statut]);
} catch (JsonException $e) {
    sendJsonResponse(['erreur' => 'JSON invalide.'], 400);
} catch (Throwable $e) {
    error_log('Échec de modération d’avis : ' . $e->getMessage()); sendJsonResponse(['erreur' => 'Erreur interne.'], 500);
}
