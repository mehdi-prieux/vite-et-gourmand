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
        $sql = "SELECT
                    m.menu_id, m.titre, m.description, m.theme, m.regime,
                    m.nombre_personnes_min, m.prix, m.conditions_menu,
                    m.stock, m.disponible,
                    p.plat_id, p.nom AS plat_nom,
                    p.type_plat, p.description AS plat_description,
                    a.allergene_id, a.nom AS allergene_nom
                FROM menu AS m
                LEFT JOIN menu_plat AS mp ON mp.menu_id = m.menu_id
                LEFT JOIN plat AS p ON p.plat_id = mp.plat_id
                LEFT JOIN plat_allergene AS pa ON pa.plat_id = p.plat_id
                LEFT JOIN allergene AS a ON a.allergene_id = pa.allergene_id
                ORDER BY m.menu_id, p.plat_id, a.allergene_id";

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
                    'images' => [],
                    'plats' => []
                ];
            }

            if ($row['plat_id'] !== null) {
                $platId = (int) $row['plat_id'];
                if (!isset($menus[$id]['plats'][$platId])) {
                    $menus[$id]['plats'][$platId] = [
                        'plat_id' => $row['plat_id'],
                        'nom' => $row['plat_nom'],
                        'type_plat' => $row['type_plat'],
                        'description' => $row['plat_description'],
                        'allergenes' => [],
                    ];
                }
                if ($row['allergene_id'] !== null) {
                    $menus[$id]['plats'][$platId]['allergenes'][] = [
                        'allergene_id' => $row['allergene_id'],
                        'nom' => $row['allergene_nom'],
                    ];
                }
            }
        }

        if ($menus) {
            $placeholders = implode(',', array_fill(0, count($menus), '?'));
            $images = $this->pdo->prepare(
                "SELECT image_id, menu_id, chemin_image FROM image_menu
                 WHERE menu_id IN ($placeholders) ORDER BY image_id"
            );
            $images->execute(array_keys($menus));
            foreach ($images->fetchAll(PDO::FETCH_ASSOC) as $image) {
                $menus[$image['menu_id']]['images'][] = [
                    'image_id' => $image['image_id'],
                    'chemin_image' => $image['chemin_image'],
                ];
            }
        }

        foreach ($menus as &$menu) {
            $menu['plats'] = array_values($menu['plats']);
        }
        unset($menu);

        return array_values($menus);
    }
}
