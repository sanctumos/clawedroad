<?php

declare(strict_types=1);

/**
 * E2E: Public user profile — user.php by username.
 */
final class UserProfileE2ETest extends E2ETestCase
{
    public function testGetUserProfileEmptyUsernameRedirectsToMarketplace(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'user.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testGetUserProfileWithInvalidUsernameReturns404(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'user.php', 'get' => ['username' => 'nonexistent_user_xyz_123'], 'post' => [], 'headers' => []]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testGetUserProfileWithValidUsernameReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'user.php', 'get' => ['username' => 'admin'], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('admin', $res['body']);
    }
}
