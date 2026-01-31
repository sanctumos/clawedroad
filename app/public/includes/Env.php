<?php

declare(strict_types=1);

/**
 * Load only PHP-relevant environment variables from .env.
 * Per 08.9: PHP must NOT load Python-only secrets (mnemonic, Alchemy key,
 * commission wallets) to avoid exposure.
 */
final class Env
{
    private static ?array $vars = null;

    /** Keys PHP is allowed to read from .env */
    private const PHP_ALLOWED = [
        'DB_DRIVER',
        'DB_DSN',
        'DB_USER',
        'DB_PASSWORD',
        'SITE_URL',
        'SITE_NAME',
        'SESSION_SALT',
        'COOKIE_ENCRYPTION_SALT',
        'CSRF_SALT',
    ];

    public static function load(string $baseDir): void
    {
        if (self::$vars !== null) {
            return;
        }
        $path = $baseDir . DIRECTORY_SEPARATOR . '.env';
        $vars = [];
        if (is_file($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\"'");
                if (!in_array($name, self::PHP_ALLOWED, true)) {
                    continue;
                }
                $vars[$name] = $value;
            }
        }
        self::$vars = $vars;
    }

    public static function get(string $key): ?string
    {
        if (self::$vars === null) {
            throw new \RuntimeException('Env::load() must be called first');
        }
        return self::$vars[$key] ?? null;
    }

    public static function getRequired(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required env: {$key}");
        }
        return $v;
    }
}
