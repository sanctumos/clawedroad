<?php

declare(strict_types=1);

/**
 * API keys: plain storage in MVP, key_prefix, last_used. Validate by lookup.
 * Rate limit 60/min per key (08.9). Key inherits user role.
 */
final class ApiKey
{
    private \PDO $pdo;
    private const PREFIX_LEN = 8;          // Display prefix (first 8 chars)
    private const KEY_BYTES = 32;          // 32 random bytes
    public const KEY_HEX_LENGTH = 64;      // 32 bytes × 2 = 64 hex chars (used in Schema index)
    private const RATE_LIMIT_PER_MIN = 60;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $userUuid, string $name = ''): array
    {
        $secret = bin2hex(random_bytes(self::KEY_BYTES));
        $prefix = substr($secret, 0, self::PREFIX_LEN);
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO api_keys (user_uuid, name, api_key, key_prefix, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userUuid, $name, $secret, $prefix, $now]);
        $id = (int) $this->pdo->lastInsertId();
        return [
            'id' => $id,
            'user_uuid' => $userUuid,
            'name' => $name,
            'api_key' => $secret,
            'key_prefix' => $prefix,
            'created_at' => $now,
        ];
    }

    public function revoke(int $id, string $userUuid): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_keys WHERE id = ? AND user_uuid = ?');
        $stmt->execute([$id, $userUuid]);
        return $stmt->rowCount() > 0;
    }

    /** Validate key and return user row or null. Updates last_used_at. */
    public function validate(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT k.*, u.uuid AS user_uuid, u.username, u.role FROM api_keys k JOIN users u ON k.user_uuid = u.uuid WHERE k.api_key = ? AND u.deleted_at IS NULL');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE api_keys SET last_used_at = ? WHERE id = ?')->execute([$now, $row['id']]);
        // Normalize to 'uuid' to match Session::getUser() shape
        return [
            'uuid' => $row['user_uuid'],
            'username' => $row['username'],
            'role' => $row['role'],
            'api_key_id' => $row['id'],
        ];
    }

    public function listForUser(string $userUuid): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, key_prefix, created_at, last_used_at FROM api_keys WHERE user_uuid = ? ORDER BY created_at DESC');
        $stmt->execute([$userUuid]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Check rate limit: 60 requests per minute per key. Returns true if under limit. */
    public function checkRateLimit(int $apiKeyId): bool
    {
        $oneMinAgo = date('Y-m-d H:i:s', time() - 60);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS n FROM api_key_requests WHERE api_key_id = ? AND requested_at > ?');
        $stmt->execute([$apiKeyId, $oneMinAgo]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ((int) ($row['n'] ?? 0)) < self::RATE_LIMIT_PER_MIN;
    }

    /** Record a request for rate limiting. Prunes rows older than 2 minutes. */
    public function recordRequest(int $apiKeyId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO api_key_requests (api_key_id, requested_at) VALUES (?, ?)')->execute([$apiKeyId, $now]);
        $twoMinAgo = date('Y-m-d H:i:s', time() - 120);
        $this->pdo->prepare('DELETE FROM api_key_requests WHERE requested_at < ?')->execute([$twoMinAgo]);
    }
}
