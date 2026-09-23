<?php

declare(strict_types=1);

require_once __DIR__ . '/_response.php';
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); sendJsonResponse(['erreur' => 'Méthode non autorisée.'], 405); exit; }
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) { sendJsonResponse(['erreur' => 'Le contenu doit être au format JSON.'], 415); exit; }

try {
    require_once __DIR__ . '/../config/session.php'; startSecureSession();
    $staffId = $_SESSION['utilisateur_id'] ?? null;
    if (!is_int($staffId) || $staffId < 1 || !in_array($_SESSION['role'] ?? null, ['employe', 'administrateur'], true)) { sendJsonResponse(['erreur' => 'Accès réservé au personnel.'], 403); exit; }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { sendJsonResponse(['erreur' => 'Jeton CSRF invalide.'], 403); exit; }
    require __DIR__ . '/../config/database.php';
    $check = $pdo->prepare('SELECT actif, role FROM utilisateur WHERE utilisateur_id = :id'); $check->execute(['id' => $staffId]); $staff = $check->fetch(PDO::FETCH_ASSOC);
    if (!$staff || !(bool) $staff['actif'] || !in_array($staff['role'], ['employe', 'administrateur'], true)) { sendJsonResponse(['erreur' => 'Accès refusé.'], 403); exit; }
    $input = json_decode(file_get_contents('php://input') ?: '', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) { sendJsonResponse(['erreur' => 'Un objet JSON est attendu.'], 400); exit; }
    $action = $input['action'] ?? null;

    if ($action === 'sauvegarder_menu') {
        $id = filter_var($input['menu_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $required = ['titre' => 150, 'description' => 5000, 'theme' => 50, 'regime' => 50, 'conditions_menu' => 5000]; $values = [];
        foreach ($required as $field => $limit) { $value = is_string($input[$field] ?? null) ? trim($input[$field]) : ''; if ($value === '' || strlen($value) > $limit) { sendJsonResponse(['erreur' => 'Informations du menu invalides.'], 422); exit; } $values[$field] = $value; }
        $minimum = filter_var($input['nombre_personnes_min'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $stock = filter_var($input['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        $prix = filter_var($input['prix'] ?? null, FILTER_VALIDATE_FLOAT);
        $disponible = filter_var($input['disponible'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($minimum === false || $stock === false || $prix === false || $prix < 0 || $prix > 1000000 || $disponible === null) { sendJsonResponse(['erreur' => 'Tarif, stock ou disponibilité invalide.'], 422); exit; }
        $values += ['minimum' => $minimum, 'prix' => number_format($prix, 2, '.', ''), 'stock' => $stock, 'disponible' => $disponible ? 1 : 0];
        if ($id === false || $id === null) {
            $stmt = $pdo->prepare('INSERT INTO menu (titre, description, theme, regime, nombre_personnes_min, prix, conditions_menu, stock, disponible) VALUES (:titre,:description,:theme,:regime,:minimum,:prix,:conditions_menu,:stock,:disponible)');
            $stmt->execute($values); $id = (int) $pdo->lastInsertId(); $status = 201;
        } else {
            $values['id'] = $id; $stmt = $pdo->prepare('UPDATE menu SET titre=:titre,description=:description,theme=:theme,regime=:regime,nombre_personnes_min=:minimum,prix=:prix,conditions_menu=:conditions_menu,stock=:stock,disponible=:disponible WHERE menu_id=:id');
            $stmt->execute($values); if ($stmt->rowCount() === 0) { $exists=$pdo->prepare('SELECT 1 FROM menu WHERE menu_id=?'); $exists->execute([$id]); if (!$exists->fetchColumn()) { sendJsonResponse(['erreur' => 'Menu introuvable.'], 404); exit; } } $status = 200;
        }
        sendJsonResponse(['message' => 'Menu enregistré.', 'menu_id' => $id], $status); exit;
    }
    if ($action === 'supprimer_menu') {
        $id = filter_var($input['menu_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); if ($id === false || $id === null) { sendJsonResponse(['erreur' => 'Menu invalide.'], 422); exit; }
        $stmt=$pdo->prepare('DELETE FROM menu WHERE menu_id=?'); $stmt->execute([$id]); if ($stmt->rowCount()!==1) { sendJsonResponse(['erreur' => 'Menu introuvable.'],404); exit; } sendJsonResponse(['message'=>'Menu supprimé.']); exit;
    }
    if ($action === 'sauvegarder_plat') {
        $id=filter_var($input['plat_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $nom=is_string($input['nom']??null)?trim($input['nom']):''; $type=$input['type_plat']??null; $description=is_string($input['description']??null)?trim($input['description']):'';
        if($nom===''||strlen($nom)>100||!in_array($type,['entrée','plat','dessert'],true)||strlen($description)>5000){sendJsonResponse(['erreur'=>'Informations du plat invalides.'],422);exit;}
        $allergenIds=$input['allergene_ids']??null;if($allergenIds!==null&&(!is_array($allergenIds)||count($allergenIds)>50)){sendJsonResponse(['erreur'=>'Liste des allergènes invalide.'],422);exit;}
        if(is_array($allergenIds)){$allergenIds=array_values(array_unique(array_map('intval',$allergenIds)));if(array_filter($allergenIds,fn($value)=>$value<1)){sendJsonResponse(['erreur'=>'Liste des allergènes invalide.'],422);exit;}}
        $pdo->beginTransaction();
        if($id===false||$id===null){$stmt=$pdo->prepare('INSERT INTO plat (nom,type_plat,description) VALUES (?,?,?)');$stmt->execute([$nom,$type,$description]);$id=(int)$pdo->lastInsertId();$status=201;}else{$stmt=$pdo->prepare('UPDATE plat SET nom=?,type_plat=?,description=? WHERE plat_id=?');$stmt->execute([$nom,$type,$description,$id]);$status=200;}
        if(is_array($allergenIds)){$pdo->prepare('DELETE FROM plat_allergene WHERE plat_id=?')->execute([$id]);$link=$pdo->prepare('INSERT INTO plat_allergene (plat_id,allergene_id) VALUES (?,?)');foreach($allergenIds as $allergenId)$link->execute([$id,$allergenId]);}
        $pdo->commit();
        sendJsonResponse(['message'=>'Plat enregistré.','plat_id'=>$id],$status);exit;
    }
    if ($action === 'supprimer_plat') {
        $id=filter_var($input['plat_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false||$id===null){sendJsonResponse(['erreur'=>'Plat invalide.'],422);exit;}$pdo->beginTransaction();$pdo->prepare('DELETE FROM plat_allergene WHERE plat_id=?')->execute([$id]);$stmt=$pdo->prepare('DELETE FROM plat WHERE plat_id=?');$stmt->execute([$id]);if($stmt->rowCount()!==1){$pdo->rollBack();sendJsonResponse(['erreur'=>'Plat introuvable.'],404);exit;}$pdo->commit();sendJsonResponse(['message'=>'Plat supprimé.']);exit;
    }
    if ($action === 'associer_plats') {
        $menuId=filter_var($input['menu_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$platIds=$input['plat_ids']??null;
        if($menuId===false||$menuId===null||!is_array($platIds)||count($platIds)>100){sendJsonResponse(['erreur'=>'Association invalide.'],422);exit;}$platIds=array_values(array_unique(array_map('intval',$platIds)));if(array_filter($platIds,fn($v)=>$v<1)){sendJsonResponse(['erreur'=>'Association invalide.'],422);exit;}
        $pdo->beginTransaction();$pdo->prepare('DELETE FROM menu_plat WHERE menu_id=?')->execute([$menuId]);$insert=$pdo->prepare('INSERT INTO menu_plat (menu_id,plat_id) VALUES (?,?)');foreach($platIds as $platId)$insert->execute([$menuId,$platId]);$pdo->commit();sendJsonResponse(['message'=>'Composition du menu mise à jour.']);exit;
    }
    if ($action === 'sauvegarder_images') {
        $menuId=filter_var($input['menu_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$paths=$input['chemins']??null;
        if($menuId===false||$menuId===null||!is_array($paths)||count($paths)>10){sendJsonResponse(['erreur'=>'Galerie invalide.'],422);exit;}
        $paths=array_values(array_unique(array_map(fn($path)=>is_string($path)?trim($path):'', $paths)));
        foreach($paths as $path){if($path===''||strlen($path)>255||(!preg_match('#^https://#i',$path)&&!preg_match('#^(?:\.?\.?/)?assets/[A-Za-z0-9_./-]+$#',$path))){sendJsonResponse(['erreur'=>'Chemin d’image invalide. Utilisez une URL HTTPS ou un chemin sous assets/.'],422);exit;}}
        $pdo->beginTransaction();$pdo->prepare('DELETE FROM image_menu WHERE menu_id=?')->execute([$menuId]);$insert=$pdo->prepare('INSERT INTO image_menu (menu_id,chemin_image) VALUES (?,?)');foreach($paths as $path)$insert->execute([$menuId,$path]);$pdo->commit();sendJsonResponse(['message'=>'Galerie du menu mise à jour.']);exit;
    }
    sendJsonResponse(['erreur' => 'Action inconnue.'], 422);
} catch (JsonException $e) { sendJsonResponse(['erreur'=>'JSON invalide.'],400);
} catch (PDOException $e) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack(); if($e->getCode()==='23000')sendJsonResponse(['erreur'=>'Suppression impossible : cet élément est encore utilisé.'],409);else{error_log('Échec catalogue personnel : '.$e->getMessage());sendJsonResponse(['erreur'=>'Erreur interne.'],500);}
} catch (Throwable $e) { if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Échec catalogue personnel : '.$e->getMessage());sendJsonResponse(['erreur'=>'Erreur interne.'],500); }
