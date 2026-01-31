<?php

declare(strict_types=1);

/**
 * PHP session wrapper. Uses SESSION_SALT from env for session name (08.9).
 * File-backed by default; DB-backed optional.
 */
final class Session
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function start(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            return;
        }
        Env::load($this->baseDir);
        $salt = Env::get('SESSION_SALT') ?? 'default-salt';
        $name = 'store_' . substr(hash('sha256', $salt), 0, 16);
        if (session_status() === \PHP_SESSION_NONE) {
            session_name($name);
            session_start();
        }
    }

    public function setUser(array $user): void
    {
        $this->start();
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
    }

    public function getUser(): ?array
    {
        $this->start();
        if (empty($_SESSION['user_uuid'])) {
            return null;
        }
        return [
            'uuid' => $_SESSION['user_uuid'],
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? 'customer',
        ];
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
