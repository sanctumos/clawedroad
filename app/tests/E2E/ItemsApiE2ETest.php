<?php

declare(strict_types=1);

final class ItemsApiE2ETest extends E2ETestCase
{
    public function testGetItemsReturnsJson(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/items.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
    }

    public function testGetItemsWithStoreUuidFilter(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/items.php', 'get' => ['store_uuid' => 'some-uuid'], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
    }

    public function testPostItemsWithoutSessionReturns401(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/items.php', 'get' => [], 'post' => ['name' => 'Item', 'store_uuid' => 'x'], 'headers' => []]);
        $this->assertSame(401, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Login required', $data['error'] ?? '');
    }

    public function testInvalidMethodReturns405(): void
    {
        $res = self::runRequest(['method' => 'DELETE', 'uri' => 'api/items.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(405, $res['code']);
    }

    public function testPostItemsWithSessionButWithoutCsrfReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login should succeed');

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/items.php',
            'get' => [],
            'post' => ['name' => 'TestItem', 'store_uuid' => 'fake-store'],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('CSRF token required', $data['error'] ?? '');
    }

    public function testPostItemsWithSessionAndCsrfSucceeds(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login should succeed');

        // Get CSRF token from register page
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

        // First create a store (needed for item)
        $storename = 'IT' . substr(md5((string) time()), 0, 8);
        $storeRes = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storename, 'description' => 'Test', 'csrf_token' => $csrf],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $storeRes['code']);
        $storeData = json_decode($storeRes['body'], true);
        $storeUuid = $storeData['uuid'] ?? '';
        $this->assertNotEmpty($storeUuid);

        // Now create item
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/items.php',
            'get' => [],
            'post' => ['name' => 'TestItem', 'store_uuid' => $storeUuid, 'csrf_token' => $csrf],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
        $this->assertNotEmpty($data['uuid'] ?? '');
    }
}
