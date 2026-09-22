<?php

class Menu
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getMenu()
    {
        // LEFT JOIN preserves menus without any associated dishes.
        $sql = "SELECT
                    m.menu_id, m.titre, m.description, m.theme, m.regime,
                    m.nombre_personnes_min, m.prix, m.conditions_menu,
                    m.stock, m.disponible,
                    p.plat_id, p.nom AS plat_nom,
                    p.type_plat, p.description AS plat_description
                FROM menu AS m
                LEFT JOIN menu_plat AS mp ON mp.menu_id = m.menu_id
                LEFT JOIN plat AS p ON p.plat_id = mp.plat_id
                ORDER BY m.menu_id, p.plat_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $menus = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = $row['menu_id'];
            if (!isset($menus[$id])) {
                $menus[$id] = [
                    'menu_id' => $row['menu_id'],
                    'titre' => $row['titre'],
                    'description' => $row['description'],
                    'theme' => $row['theme'],
                    'regime' => $row['regime'],
                    'nombre_personnes_min' => $row['nombre_personnes_min'],
                    'prix' => $row['prix'],
                    'conditions_menu' => $row['conditions_menu'],
                    'stock' => $row['stock'],
                    'disponible' => $row['disponible'],
                    'plats' => []
                ];
            }

            if ($row['plat_id'] !== null) {
                $menus[$id]['plats'][] = [
                    'plat_id' => $row['plat_id'],
                    'nom' => $row['plat_nom'],
                    'type_plat' => $row['type_plat'],
                    'description' => $row['plat_description']
                ];
            }
        }

        return array_values($menus);
    }
}
