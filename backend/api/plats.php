<?php

header("Content-Type: application/json");

require_once "../config/database.php";
require_once "../controllers/PlatController.php";


$controller = new PlatController($pdo);

$data = $controller->index();


echo json_encode($data);

?>