<?php
// backend/db.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('db')) {
    function db(): PDO {
        static $pdo;
        if ($pdo instanceof PDO) return $pdo;

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'cafforam_db'; // hosting DB
        $user = defined('DB_USER') ? DB_USER : 'cafforam_dhyuncode';
        $pass = defined('DB_PASS') ? DB_PASS : 'Uroh120202';
        $port = getenv('DB_PORT') ?: '3306';

        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $opt  = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $opt);
        } catch (PDOException $e) {
            die("DB Error (PDO): " . $e->getMessage());
        }

        return $pdo;
    }
}
