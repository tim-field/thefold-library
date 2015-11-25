<?php

namespace TheFold\WordPress;

use TheFold\Singleton;

class PDO {

    use Singleton;

    public $pdo;

    function __construct(){
        $this->pdo = new \PDO(sprintf('mysql:dbname=%s;host=%s',\DB_NAME,\DB_HOST), \DB_USER, \DB_PASSWORD);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
}
