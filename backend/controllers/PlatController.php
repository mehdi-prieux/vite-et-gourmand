<?php

require_once __DIR__ . '/../models/Plat.php';

class PlatController
{

    private $plat;

    public function __construct($database)
    {
        $this->plat = new Plat($database);
    }


    public function index()
    {
        return $this->plat->getAll();
    }

}

?>