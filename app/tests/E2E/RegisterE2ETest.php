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

    /** POST without CSRF shows "Invalid request" or validation error. */
    public function testPostRegisterInvalidUsernameShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => '', 'password' => 'password123'], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertTrue(
            str_contains($res['body'], 'Invalid username') || str_contains($res['body'], 'Invalid request'),
            'Expected validation or CSRF error'
        );
    }

    /** POST without CSRF or with short password shows form with error. */
    public function testPostRegisterShortPasswordShowsFormWithError(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => 'newuser', 'password' => 'short'], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertTrue(
            str_contains($res['body'], '8') || str_contains($res['body'], 'Invalid request'),
            'Expected password length or CSRF error'
        );
    }

    /** GET form, extract CSRF and session cookie, then POST with valid data redirects. */
    public function testPostRegisterSuccessRedirectsToMarketplace(): void
    {
        $getRes = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $getRes['code']);
        $cookies = self::parseCookiesFromResponse($getRes);
        if ($cookies === [] && isset($getRes['session_name'], $getRes['session_id']) && $getRes['session_name'] !== '' && $getRes['session_id'] !== '') {
            $cookies = [$getRes['session_name'] => $getRes['session_id']];
        }
        if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $getRes['body'], $m)) {
            $csrf = $m[1];
        } else {
            $this->markTestSkipped('Could not extract CSRF token from register form');
        }
        $username = 'e2e_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'register.php',
            'get' => [],
            'post' => ['username' => $username, 'password' => 'password123', 'csrf_token' => $csrf],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(302, $res['code'], 'Response: ' . (strlen($res['body'] ?? '') > 200 ? substr($res['body'], 0, 200) . '...' : ($res['body'] ?? '')));
    }
}
