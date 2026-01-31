<?php

declare(strict_types=1);

/**
 * User model: username, password hash, roles (admin/vendor/customer). No PGP, no 2FA (04, 08).
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_VENDOR = 'vendor';
    public const ROLE_CUSTOMER = 'customer';

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $uuid, string $username, string $passwordPlain, string $role = self::ROLE_CUSTOMER, ?string $inviterUuid = null): ?array
    {
        $hash = password_hash($passwordPlain, PASSWORD_BCRYPT, ['cost' => 12]);
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, inviter_uuid, banned, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)');
        $stmt->execute([$uuid, $username, $hash, $role, $inviterUuid, $now]);
        return $this->findByUuid($uuid);
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE uuid = ? AND deleted_at IS NULL');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL');
        $stmt->execute([$username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function verifyPassword(string $username, string $passwordPlain): ?array
    {
        $user = $this->findByUsername($username);
        if ($user === null || !password_verify($passwordPlain, $user['passphrase_hash']) || !empty($user['banned'])) {
            return null;
        }
        return $user;
    }

    public function updateLastLogin(string $uuid): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET updated_at = ? WHERE uuid = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $uuid]);
    }

    public static function generateUuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
