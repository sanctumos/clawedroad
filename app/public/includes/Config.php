<?php

declare(strict_types=1);

/**
 * Config/settings table per 01 §12. Defaults are admin-configurable.
 */
final class Config
{
    private \PDO $pdo;
    private array $cache = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Seed default config keys (idempotent: only insert if key missing). */
    public function seedDefaults(): void
    {
        $defaults = [
            'pending_duration' => '24h',
            'completed_duration' => '336h',
            'stuck_duration' => '720h',
            'completion_tolerance' => '0.05',
            'partial_refund_resolver_percent' => '0.10',
            'gold_account_commission' => '0.02',
            'silver_account_commission' => '0.05',
            'bronze_account_commission' => '0.10',
            'free_account_commission' => '0.20',
            'gold_account_referral_percent' => '0.50',
            'silver_account_referral_percent' => '0.50',
            'bronze_account_referral_percent' => '0.50',
            'free_account_referral_percent' => '0.50',
            'android_developer_username' => '',
            'android_developer_commission' => '0',
        ];
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)');
        } else {
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO config (key, value) VALUES (?, ?)');
        }
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    public function get(string $key): ?string
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $stmt = $this->pdo->prepare('SELECT value FROM config WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $v = $row ? $row['value'] : null;
        $this->cache[$key] = $v;
        return $v;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $v = $this->get($key);
        return $v !== null ? (float) $v : $default;
    }

    public function set(string $key, string $value): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare('INSERT INTO config (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        } else {
            $stmt = $this->pdo->prepare('REPLACE INTO config (key, value) VALUES (?, ?)');
        }
        $stmt->execute([$key, $value]);
        $this->cache[$key] = $value;
    }
}
