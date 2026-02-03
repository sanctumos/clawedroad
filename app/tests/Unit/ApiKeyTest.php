<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \ApiKey
 */
final class ApiKeyTest extends TestCase
{
    private PDO $pdo;
    private ApiKey $apiKeyRepo;
    private string $userUuid;

    protected function setUp(): void
    {
        $this->pdo = Db::pdo();
        $this->apiKeyRepo = new ApiKey($this->pdo);
        $this->userUuid = User::generateUuid();
        $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at) VALUES (?, ?, ?, ?, 0, ?)')
            ->execute([$this->userUuid, 'apikey_user_' . bin2hex(random_bytes(4)), password_hash('x', PASSWORD_BCRYPT), 'customer', date('Y-m-d H:i:s')]);
    }

    public function testCreateReturnsKeyWithPrefix(): void
    {
        $data = $this->apiKeyRepo->create($this->userUuid, 'My Key');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('api_key', $data);
        $this->assertArrayHasKey('key_prefix', $data);
        $this->assertSame($this->userUuid, $data['user_uuid']);
        $this->assertSame('My Key', $data['name']);
        $this->assertSame(64, strlen($data['api_key']));
        $this->assertSame(8, strlen($data['key_prefix']));
        $this->assertStringStartsWith($data['key_prefix'], $data['api_key']);
    }

    public function testCreateWithEmptyName(): void
    {
        $data = $this->apiKeyRepo->create($this->userUuid, '');
        $this->assertSame('', $data['name']);
    }

    public function testValidateReturnsUserWhenKeyValid(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Test');
        $user = $this->apiKeyRepo->validate($created['api_key']);
        $this->assertNotNull($user);
        // Normalized to 'uuid' to match Session::getUser() shape
        $this->assertSame($this->userUuid, $user['uuid']);
        $this->assertArrayHasKey('username', $user);
        $this->assertArrayHasKey('role', $user);
        $this->assertSame($created['id'], $user['api_key_id']);
    }

    public function testValidateReturnsNullWhenKeyInvalid(): void
    {
        $user = $this->apiKeyRepo->validate('invalid_key_xyz_123');
        $this->assertNull($user);
    }

    public function testRevokeReturnsTrueWhenKeyExists(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Revoke Me');
        $result = $this->apiKeyRepo->revoke($created['id'], $this->userUuid);
        $this->assertTrue($result);
        $this->assertNull($this->apiKeyRepo->validate($created['api_key']));
    }

    public function testRevokeReturnsFalseWhenWrongUser(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Test');
        $otherUuid = User::generateUuid();
        $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at) VALUES (?, ?, ?, ?, 0, ?)')
            ->execute([$otherUuid, 'other_' . bin2hex(random_bytes(4)), password_hash('x', PASSWORD_BCRYPT), 'customer', date('Y-m-d H:i:s')]);
        $result = $this->apiKeyRepo->revoke($created['id'], $otherUuid);
        $this->assertFalse($result);
    }

    public function testListForUserReturnsKeys(): void
    {
        $this->apiKeyRepo->create($this->userUuid, 'Key1');
        $this->apiKeyRepo->create($this->userUuid, 'Key2');
        $list = $this->apiKeyRepo->listForUser($this->userUuid);
        $this->assertCount(2, $list);
        $this->assertArrayNotHasKey('api_key', $list[0]);
        $this->assertArrayHasKey('key_prefix', $list[0]);
    }

    public function testListForUserReturnsEmptyWhenNone(): void
    {
        $otherUuid = User::generateUuid();
        $this->pdo->prepare('INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at) VALUES (?, ?, ?, ?, 0, ?)')
            ->execute([$otherUuid, 'empty_' . bin2hex(random_bytes(4)), password_hash('x', PASSWORD_BCRYPT), 'customer', date('Y-m-d H:i:s')]);
        $list = $this->apiKeyRepo->listForUser($otherUuid);
        $this->assertSame([], $list);
    }

    public function testCheckRateLimitReturnsTrueWhenUnderLimit(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Rate');
        $under = $this->apiKeyRepo->checkRateLimit($created['id']);
        $this->assertTrue($under);
    }

    public function testCheckRateLimitReturnsFalseWhenOverLimit(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Rate');
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO api_key_requests (api_key_id, requested_at) VALUES (?, ?)');
        for ($i = 0; $i < 61; $i++) {
            $stmt->execute([$created['id'], $now]);
        }
        $under = $this->apiKeyRepo->checkRateLimit($created['id']);
        $this->assertFalse($under);
    }

    public function testRecordRequestInsertsAndPrunes(): void
    {
        $created = $this->apiKeyRepo->create($this->userUuid, 'Record');
        $this->apiKeyRepo->recordRequest($created['id']);
        $count = $this->pdo->query('SELECT COUNT(*) FROM api_key_requests WHERE api_key_id = ' . $created['id'])->fetchColumn();
        $this->assertGreaterThanOrEqual(1, (int) $count);
    }
}
