<?php

declare(strict_types=1);

final class RegisterE2ETest extends E2ETestCase
{
    public function testGetRegisterReturnsHtmlForm(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('register.php', $res['body']);
    }

    public function testPostRegisterInvalidUsernameShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => '', 'password' => 'password123'], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Invalid username', $res['body']);
    }

    public function testPostRegisterShortPasswordShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => 'newuser', 'password' => 'short'], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('at least 8', $res['body']);
    }

    public function testPostRegisterSuccessRedirectsToMarketplace(): void
    {
        $username = 'e2e_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $res = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => $username, 'password' => 'password123'], 'headers' => []]);
        $this->assertSame(302, $res['code'], 'Response: ' . ($res['body'] ?? ''));
    }
}
