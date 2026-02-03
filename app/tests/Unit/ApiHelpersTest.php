<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests requireApiKeyAndRateLimit, requireSession, requireAdmin (api_helpers.php).
 * These functions call exit() on failure - we test via output/headers or by passing valid auth.
 */
final class ApiHelpersTest extends TestCase
{
    private PDO $pdo;
    private object $session;
    private object $apiKeyRepo;
    private string $userUuid;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->pdo = Db::pdo();
        $this->session = new Session(TEST_BASE_DIR);
        $this->apiKeyRepo = new ApiKey($this->pdo);
        $this->userUuid = User::generateUuid();
        $userRepo = new User($this->pdo);
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at) VALUES (?, ?, ?, ?, 0, ?)')
            ->execute([$this->userUuid, 'helper_user_' . bin2hex(random_bytes(4)), password_hash('x', PASSWORD_BCRYPT), 'customer', $now]);
        $created = $this->apiKeyRepo->create($this->userUuid, 'Test');
        $this->apiKey = $created['api_key'];
    }

    public function testRequireSessionReturnsUserWhenLoggedIn(): void
    {
        $this->session->start();
        $this->session->setUser(['uuid' => $this->userUuid, 'username' => 'test', 'role' => 'customer']);
        $user = requireSession($this->session);
        $this->assertSame($this->userUuid, $user['uuid']);
    }

    public function testRequireApiKeyAndRateLimitReturnsUserWhenValid(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->apiKey;
        $user = requireApiKeyAndRateLimit($this->apiKeyRepo);
        // Normalized to 'uuid' to match Session::getUser() shape
        $this->assertSame($this->userUuid, $user['uuid']);
    }

    public function testRequireAgentOrApiKeyReturnsUserWhenAgentTokenValid(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $_SERVER['HTTP_X_AGENT_IDENTITY'] = 'agent-unit';
        $agentIdentity = new AgentIdentity($this->pdo, new User($this->pdo));
        $hooks = new Hooks($this->pdo);
        $user = requireAgentOrApiKey($agentIdentity, $this->apiKeyRepo, $this->pdo, $hooks);
        $this->assertSame('customer', $user['role']);
        $this->assertNotEmpty($user['uuid']);
    }

    public function testRequireAdminReturnsUserWhenAdmin(): void
    {
        $this->pdo->prepare('UPDATE users SET role = ? WHERE uuid = ?')->execute(['admin', $this->userUuid]);
        $this->session->start();
        $this->session->setUser(['uuid' => $this->userUuid, 'username' => 'admin', 'role' => 'admin']);
        $user = requireAdmin($this->session);
        $this->assertSame('admin', $user['role']);
    }
}
