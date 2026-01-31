<?php

declare(strict_types=1);

/**
 * DB abstraction: PDO with SQLite or MariaDB from .env (portable schema).
 */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function init(string $baseDir): void
    {
        if (self::$pdo !== null) {
            return;
        }
        Env::load($baseDir);
        $driver = strtolower(Env::getRequired('DB_DRIVER'));
        $dsn = Env::getRequired('DB_DSN');
        if ($driver === 'sqlite') {
            $path = substr($dsn, 7);
            if ($path !== '' && $path[0] !== '/' && preg_match('#^[a-z]:#i', $path) !== 1) {
                $path = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            }
            $dsn = 'sqlite:' . $path;
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            self::$pdo = new \PDO($dsn, null, null, $options);
        } elseif ($driver === 'mariadb' || $driver === 'mysql') {
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ];
            $user = Env::get('DB_USER') ?? '';
            $pass = Env::get('DB_PASSWORD') ?? '';
            self::$pdo = new \PDO($dsn, $user ?: null, $pass ?: null, $options);
        } else {
            throw new \RuntimeException('Unsupported DB_DRIVER: ' . $driver);
        }
    }

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Db::init() must be called first');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Db::init() must be called first');
        }
        return self::$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /** Whether current driver is SQLite (for portable DDL). */
    public static function isSqlite(): bool
    {
        return strtolower(self::driver()) === 'sqlite';
    }
}
