<?php

require_once __DIR__ . '/../config/database.php';

class Plat
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function getAll()
    {
        $query = "SELECT * FROM plat";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>