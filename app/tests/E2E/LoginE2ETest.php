<?php

declare(strict_types=1);

final class LoginE2ETest extends E2ETestCase
{
    public function testGetLoginReturnsHtmlForm(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'login.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('login.php', $res['body']);
        $this->assertStringContainsString('username', $res['body']);
        $this->assertStringContainsString('password', $res['body']);
    }

    public function testPostLoginMissingCredentialsShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'login.php', 'get' => [], 'post' => ['username' => '', 'password' => ''], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Missing', $res['body']);
    }

    public function testPostLoginInvalidCredentialsShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'login.php', 'get' => [], 'post' => ['username' => 'nonexistent_user_xyz', 'password' => 'wrong'], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Invalid', $res['body']);
    }

    public function testPostLoginValidCredentialsRedirectsAndSessionAvailable(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'login.php', 'get' => [], 'post' => ['username' => 'admin', 'password' => 'admin'], 'headers' => []]);
        $this->assertSame(302, $res['code']);
        $cookies = self::parseCookiesFromResponse($res);
        if ($cookies === [] && isset($res['session_name'], $res['session_id'])) {
            $this->assertNotEmpty($res['session_name']);
            $this->assertNotEmpty($res['session_id']);
        } else {
            $this->assertNotEmpty($cookies, 'Expected Set-Cookie or session_name/session_id after login');
        }
    }
}
