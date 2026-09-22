<?php

require_once __DIR__ . "/../models/Menu.php";


class MenuController {

    private $menu;


    public function __construct($pdo)
    {
        $this->menu = new Menu($pdo);
    }


    public function index()
    {
        return $this->menu->getMenu();
    }

}