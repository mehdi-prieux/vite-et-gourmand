<?php

require_once __DIR__ . '/../config/database.php';

class Plat
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.plat_id, p.nom, p.type_plat, p.description,
                    a.allergene_id, a.nom AS allergene_nom
             FROM plat p
             LEFT JOIN plat_allergene pa ON pa.plat_id = p.plat_id
             LEFT JOIN allergene a ON a.allergene_id = pa.allergene_id
             ORDER BY p.plat_id, a.nom'
        );
        $stmt->execute();
        $dishes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['plat_id'];
            if (!isset($dishes[$id])) {
                $dishes[$id] = [
                    'plat_id' => $id,
                    'nom' => $row['nom'],
                    'type_plat' => $row['type_plat'],
                    'description' => $row['description'],
                    'allergenes' => [],
                ];
            }
            if ($row['allergene_id'] !== null) {
                $dishes[$id]['allergenes'][] = [
                    'allergene_id' => (int) $row['allergene_id'],
                    'nom' => $row['allergene_nom'],
                ];
            }
        }
        return array_values($dishes);
    }
}

?>
