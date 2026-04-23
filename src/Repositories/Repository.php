<?php

namespace FurEver\Repositories;

use FurEver\Core\Database;
use PDO;

abstract class Repository
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
}
