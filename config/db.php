<?php

class Database {

    private static $host = "localhost";
    private static $port = "3306";
    private static $db   = "multibusiness_saas";
    private static $user = "root";
    private static $pass = "";

    public static function getConnection() {

        try {

            $conn = new PDO(
                "mysql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$db . ";charset=utf8mb4",
                self::$user,
                self::$pass
            );

            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $conn;

        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}
?>