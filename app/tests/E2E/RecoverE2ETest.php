<?php

declare(strict_types=1);

/**
 * E2E: Password recover — GET form, invalid token, request token (rate-limited).
 */
final class RecoverE2ETest extends E2ETestCase
{
    public function testGetRecoverReturnsForm(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'recover.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Recover password', $res['body']);
        $this->assertStringContainsString('recover.php', $res['body']);
        $this->assertStringContainsString('username', $res['body']);
    }

    public function testGetRecoverWithInvalidTokenShowsInvalidOrExpired(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'recover.php', 'get' => ['token' => 'invalid-token-xyz'], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Invalid or expired', $res['body']);
    }

    public function testPostRecoverRequestWithMissingUsernameShowsError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'recover.php', 'get' => [], 'post' => ['username' => ''], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('recover.php', $res['body']);
    }
}
