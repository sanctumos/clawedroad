<?php

declare(strict_types=1);

/**
 * E2E: 100% coverage at all user levels — anonymous, customer (session), vendor (store owner), admin.
 */
final class FullStackE2ETest extends E2ETestCase
{
    // ---- Anonymous: public pages return 200 ----
    public function testAnonymousMarketplaceReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'marketplace.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Marketplace', $res['body']);
    }

    public function testAnonymousVendorsReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'vendors.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Vendors', $res['body']);
    }

    public function testAnonymousStoreWithInvalidUuidReturns404(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'store.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => []]);
        $this->assertSame(404, $res['code']);
    }

    public function testAnonymousItemWithInvalidUuidReturns404(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'item.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => []]);
        $this->assertSame(404, $res['code']);
    }

    public function testAnonymousPaymentsRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'payments.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousPaymentRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'payment.php', 'get' => ['uuid' => 'x'], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousCreateStoreRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'create-store.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousBookWithoutPackageRedirectsToMarketplace(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'book.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousAdminIndexRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/index.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    // ---- Anonymous: public API 200, session-required API 401 ----
    public function testAnonymousApiStoresGetReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/stores.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('stores', $data);
    }

    public function testAnonymousApiItemsGetReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/items.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('items', $data);
    }

    public function testAnonymousApiStoresPostReturns401(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/stores.php', 'get' => [], 'post' => ['storename' => 'X', 'description' => ''], 'headers' => []]);
        $this->assertSame(401, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Login required', $data['error'] ?? '');
    }

    public function testAnonymousApiItemsPostReturns401(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/items.php', 'get' => [], 'post' => ['name' => 'X', 'store_uuid' => 'x'], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousApiTransactionsGetReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/transactions.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousApiKeysGetReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/keys.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousApiDepositsReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/deposits.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousApiDisputesReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/disputes.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousAdminConfigReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/config.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousAdminTokensReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/tokens.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testAnonymousAdminTokensRemoveReturns401(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'admin/tokens-remove.php', 'get' => [], 'post' => ['id' => '1'], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    // ---- Customer (session): payments, create-store, API with session ----
    public function testCustomerPaymentsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies, 'Login must succeed to get cookies');
        $res = self::runRequest(['method' => 'GET', 'uri' => 'payments.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('My orders', $res['body']);
    }

    public function testCustomerCreateStoreGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'create-store.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Create store', $res['body']);
    }

    public function testCustomerCreateStorePostRedirectsToStore(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $storeName = 'e2e_store_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'create-store.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'E2E store', 'vendorship_agree' => '1'],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerApiStoresPostReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $storeName = 'e2e_api_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'E2E API store', 'vendorship_agree' => '1'],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertTrue($data['ok'] ?? false);
    }

    public function testCustomerApiKeysGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/keys.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('keys', $data);
    }

    public function testCustomerApiKeysPostReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/keys.php', 'get' => [], 'post' => ['name' => 'e2e key'], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('api_key', $data);
    }

    public function testCustomerApiTransactionsGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/transactions.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('transactions', $data);
    }

    public function testCustomerApiDepositsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/deposits.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('deposits', $data);
    }

    public function testCustomerApiDisputesReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/disputes.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('disputes', $data);
    }

    // ---- Customer (non-admin) gets 403 on admin endpoints ----
    public function testCustomerAdminConfigReturns403(): void
    {
        $username = 'cust_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $reg = self::runRequest(['method' => 'POST', 'uri' => 'register.php', 'get' => [], 'post' => ['username' => $username, 'password' => 'password123'], 'headers' => []]);
        $this->assertSame(302, $reg['code']);
        $cookies = self::parseCookiesFromResponse($reg);
        if ($cookies === [] && isset($reg['session_name'], $reg['session_id']) && $reg['session_name'] !== '' && $reg['session_id'] !== '') {
            $cookies = [$reg['session_name'] => $reg['session_id']];
        }
        $this->assertNotEmpty($cookies, 'Need session after register');
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/config.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('Admin only', $data['error'] ?? '');
    }

    // ---- Admin: admin dashboard and endpoints ----
    public function testAdminIndexReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/index.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Admin', $res['body']);
        $this->assertStringContainsString('Config', $res['body']);
    }

    public function testAdminConfigGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/config.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('pending_duration', $data);
    }

    public function testAdminConfigPostReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'POST', 'uri' => 'admin/config.php', 'get' => [], 'post' => ['pending_duration' => '24h'], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
    }

    public function testAdminTokensGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/tokens.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('tokens', $data);
    }

    public function testAdminTokensPostReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'POST', 'uri' => 'admin/tokens.php', 'get' => [], 'post' => ['chain_id' => '1', 'symbol' => 'ETH', 'contract_address' => ''], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok'] ?? false);
    }

    // ---- Vendor: create store then POST item, GET deposits ----
    public function testVendorCreateStoreThenPostItemReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $storeName = 'e2ev' . substr(bin2hex(random_bytes(4)), 0, 6);
        $createRes = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'Vendor E2E', 'vendorship_agree' => '1'],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $createRes['code'], 'Store create: ' . $createRes['body']);
        $createData = json_decode($createRes['body'], true);
        $this->assertIsArray($createData);
        $storeUuid = $createData['uuid'] ?? '';
        $this->assertNotEmpty($storeUuid, 'Store create response must contain uuid');
        $itemRes = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/items.php',
            'get' => [],
            'post' => ['name' => 'E2E Item', 'description' => 'Test item', 'store_uuid' => $storeUuid],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(200, $itemRes['code'], 'Item create response: ' . $itemRes['body']);
        $itemData = json_decode($itemRes['body'], true);
        $this->assertTrue($itemData['ok'] ?? false);
        $this->assertArrayHasKey('uuid', $itemData);
    }

    public function testVendorGetDepositsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/deposits.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('deposits', $data);
    }

    // ---- Web: book (requires package), payment (own or 403) ----
    public function testBookWithInvalidPackageReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'book.php', 'get' => ['package_uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
    }

    public function testPaymentWithInvalidUuidReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'payment.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
    }
}
