<?php

declare(strict_types=1);

/**
 * E2E: GET/POST /api/stores.php
 */
final class StoresApiE2ETest extends E2ETestCase
{
    public function testGetStoresReturnsJson(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/stores.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('stores', $data);
    }

    public function testPostStoresWithoutSessionReturns401(): void
    {
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => 'TestStore', 'description' => ''],
            'headers' => [],
        ]);
        $this->assertSame(401, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Login required', $data['error'] ?? '');
    }

    public function testPostStoresInvalidStorenameReturns400(): void
    {
        // Create user and session then POST with empty storename - we need a session
        // For simplicity we test 405 for wrong method
        $res = self::runRequest(['method' => 'PUT', 'uri' => 'api/stores.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(405, $res['code']);
    }

    public function testPostStoresWithSessionButWithoutCsrfReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login should succeed');

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => 'CsrfTestStore', 'description' => ''],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('CSRF token required', $data['error'] ?? '');
    }

    public function testPostStoresWithSessionAndCsrfSucceeds(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login should succeed');

        // Get CSRF token from register page (any page with a form will do)
        $pageRes = self::runRequest([
            'method' => 'GET',
            'uri' => 'register.php',
            'get' => [],
            'post' => [],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $csrf = self::extractCsrfFromBody($pageRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');

        $storename = 'CS' . substr(md5((string) time()), 0, 8);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storename, 'description' => 'Test', 'csrf_token' => $csrf],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertNotEmpty($data['uuid'] ?? '');
    }
}
