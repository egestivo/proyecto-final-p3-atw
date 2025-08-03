<?php
declare(strict_types=1);

namespace App\config;

use PDO;

class Database {
    // Creamos la instancia de PDO que es para la conexión a la base de datos
    private static ?PDO $instance = null;
    public static function getConnection(): PDO {
        if(self::$instance === null){
            $host = 'localhost';
            $dbName = "gestion_comercial"; 
            $user = 'admin';;
            $password = 'adminpwd';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            self::$instance = new PDO($dsn, $user, $password, $options);
        }
        return self::$instance;
    }
}