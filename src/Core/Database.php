<?php

namespace FurEver\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', 'db');
        $port = Env::get('DB_PORT', '5432');
        $name = Env::get('DB_NAME', 'furever');
        $user = Env::get('DB_USER', 'furever');
        $pass = Env::get('DB_PASSWORD', 'changeme');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function setInstance(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
