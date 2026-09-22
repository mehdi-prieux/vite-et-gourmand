<?php

class Menu {

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }


    public function getMenu()
    {
        $sql = "SELECT * FROM plat";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);


        $menu = [
            "entrees" => [],
            "plats" => [],
            "desserts" => []
        ];


        foreach ($plats as $plat) {

            switch ($plat["type_plat"]) {

                case "entrée":
                    $menu["entrees"][] = $plat;
                    break;

                case "plat":
                    $menu["plats"][] = $plat;
                    break;

                case "dessert":
                    $menu["desserts"][] = $plat;
                    break;
            }

        }

        return $menu;
    }
}