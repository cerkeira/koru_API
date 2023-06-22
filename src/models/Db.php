<?php

namespace App\Models;

use \PDO;
use PDOException;


class DB
{
    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'korudb';
    

    public function connect()
    {
        $conn_str = "mysql:host=$this->host;dbname=$this->dbname";

        try {
            $conn = new PDO($conn_str, $this->user, $this->pass);

            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $conn->exec("SET NAMES utf8mb4");

            return $conn;
        }catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            exit();
        }

        return $conn;
    }
}

