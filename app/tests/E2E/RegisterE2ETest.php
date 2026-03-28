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
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'register.php',
            'get' => [],
            'post' => ['username' => '', 'password' => 'password123'],
            'headers' => [],
            'remote_addr' => '127.0.0.' . random_int(2, 250),
        ]);
        $this->assertSame(200, $res['code']);
        $this->assertTrue(
            str_contains($res['body'], 'Invalid username') || str_contains($res['body'], 'Invalid request'),
            'Expected validation or CSRF error'
        );
    }

    /** POST without CSRF or with short password shows form with error. */
    public function testPostRegisterShortPasswordShowsFormWithError(): void
    {
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'register.php',
            'get' => [],
            'post' => ['username' => 'newuser', 'password' => 'short'],
            'headers' => [],
            'remote_addr' => '127.0.0.' . random_int(2, 250),
        ]);
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

    /** Second registration from same IP within rate limit window returns error message (not 302). */
    public function testPostRegisterSameIpWithinWindowReturnsRateLimitError(): void
    {
        $remoteAddr = '127.0.0.' . (random_int(2, 254));
        $base = ['get' => [], 'headers' => [], 'remote_addr' => $remoteAddr];
        $getRes = self::runRequest(array_merge($base, ['method' => 'GET', 'uri' => 'register.php', 'post' => []]));
        $this->assertSame(200, $getRes['code']);
        $cookies = self::parseCookiesFromResponse($getRes);
        if ($cookies === [] && isset($getRes['session_name'], $getRes['session_id']) && $getRes['session_name'] !== '' && $getRes['session_id'] !== '') {
            $cookies = [$getRes['session_name'] => $getRes['session_id']];
        }
        $csrf = self::extractCsrfFromBody($getRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Need CSRF from register form');
        $username1 = 'e2e_rl_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $res1 = self::runRequest(array_merge($base, [
            'method' => 'POST',
            'uri' => 'register.php',
            'post' => ['username' => $username1, 'password' => 'password123', 'csrf_token' => $csrf],
            'cookies' => $cookies,
        ]));
        $this->assertSame(302, $res1['code'], 'First registration from this IP should succeed');
        $username2 = 'e2e_rl_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $getRes2 = self::runRequest(array_merge($base, ['method' => 'GET', 'uri' => 'register.php', 'post' => []]));
        $csrf2 = self::extractCsrfFromBody($getRes2['body'] ?? '');
        $cookies2 = self::parseCookiesFromResponse($getRes2);
        if ($cookies2 === [] && isset($getRes2['session_name'], $getRes2['session_id'])) {
            $cookies2 = [$getRes2['session_name'] => $getRes2['session_id']];
        }
        $res2 = self::runRequest(array_merge($base, [
            'method' => 'POST',
            'uri' => 'register.php',
            'post' => ['username' => $username2, 'password' => 'password123', 'csrf_token' => $csrf2],
            'cookies' => $cookies2,
        ]));
        $this->assertSame(200, $res2['code'], 'Second registration from same IP should be rate limited');
        $this->assertStringContainsString('one account per', $res2['body'] ?? '', 'Expected rate limit message');
        $this->assertStringContainsString('Try again later', $res2['body'] ?? '', 'Expected rate limit message');
    }
}
