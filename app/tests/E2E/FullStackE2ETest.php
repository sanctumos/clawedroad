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

    public function testAnonymousReferralsRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'referrals.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousSupportRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousSupportNewRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support/new.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDepositsRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDepositsAddRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits/add.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDepositsWithdrawRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits/withdraw.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousSettingsUserRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'settings/user.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousSettingsStoreRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'settings/store.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousVerificationAgreementRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'verification/agreement.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousMessagesRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'messages.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDisputeNoUuidRedirectsToPayments(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDisputeWithUuidRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousDisputeNewRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute/new.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousReviewAddRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'review/add.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousItemEditNoUuidRedirectsToMarketplace(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'item/edit.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousItemEditWithUuidRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'item/edit.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousAdminUsersRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/users.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousStaffIndexRedirectsToLogin(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/index.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }

    public function testAnonymousVerificationPlanReturns200(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'verification/plan.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Verification', $res['body']);
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

    public function testCustomerReferralsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'referrals.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Referrals', $res['body']);
    }

    public function testCustomerSupportReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Support', $res['body']);
    }

    public function testCustomerSupportNewGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support/new.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('New ticket', $res['body']);
    }

    public function testCustomerDepositsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Deposits', $res['body']);
    }

    public function testCustomerDepositsAddGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits/add.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Add deposit', $res['body']);
    }

    public function testCustomerDepositsWithdrawNoUuidRedirectsToDeposits(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits/withdraw.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerDepositsWithdrawInvalidUuidReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'deposits/withdraw.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testCustomerMessagesReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'messages.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Messages', $res['body']);
    }

    public function testCustomerSettingsUserGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'settings/user.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('password', $res['body']);
    }

    public function testCustomerSettingsStoreGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'settings/store.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('store', $res['body']);
    }

    public function testCustomerVerificationAgreementGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'verification/agreement.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('agreement', $res['body']);
    }

    public function testCustomerDisputeWithInvalidUuidReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testCustomerDisputeNewNoTransactionRedirectsToPayments(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute/new.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerDisputeNewWithInvalidTransactionReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'dispute/new.php', 'get' => ['transaction_uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testCustomerItemEditWithInvalidUuidReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'item/edit.php', 'get' => ['uuid' => '00000000000000000000000000000000'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testCustomerReviewAddNoTransactionRedirectsToPayments(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'review/add.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerSupportTicketNoIdRedirectsToSupport(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support/ticket.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerSupportTicketInvalidIdReturns404(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'support/ticket.php', 'get' => ['id' => '999999'], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(404, $res['code']);
        $this->assertStringContainsString('not found', $res['body']);
    }

    public function testCustomerCreateStorePostRedirectsToStore(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        // Get CSRF token from create-store page
        $getRes = self::runRequest(['method' => 'GET', 'uri' => 'create-store.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $csrf = self::extractCsrfFromBody($getRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');
        $newCookies = self::parseCookiesFromResponse($getRes);
        if ($newCookies !== []) {
            $cookies = array_merge($cookies, $newCookies);
        }
        $storeName = 'e2e_store_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'create-store.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'E2E store', 'vendorship_agree' => '1', 'csrf_token' => $csrf],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(302, $res['code']);
    }

    public function testCustomerApiStoresPostReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        // Get CSRF token
        $pageRes = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $csrf = self::extractCsrfFromBody($pageRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');
        $storeName = 'e2e_api_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'E2E API store', 'vendorship_agree' => '1', 'csrf_token' => $csrf],
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
        // Get CSRF token
        $pageRes = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $csrf = self::extractCsrfFromBody($pageRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/keys.php', 'get' => [], 'post' => ['name' => 'e2e key', 'csrf_token' => $csrf], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('api_key', $data);
    }

    public function testCustomerApiKeysPostWithoutCsrfReturns403(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/keys.php', 'get' => [], 'post' => ['name' => 'e2e key'], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('CSRF token required', $data['error'] ?? '');
    }

    public function testCustomerApiTransactionsGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        // Get CSRF token
        $pageRes = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $csrf = self::extractCsrfFromBody($pageRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');
        $keyRes = self::runRequest(['method' => 'POST', 'uri' => 'api/keys.php', 'get' => [], 'post' => ['name' => 'e2e tx key', 'csrf_token' => $csrf], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $keyRes['code']);
        $keyData = json_decode($keyRes['body'], true);
        $apiKey = $keyData['api_key'] ?? '';
        $this->assertNotSame('', $apiKey);
        $res = self::runRequest([
            'method' => 'GET',
            'uri' => 'api/transactions.php',
            'get' => [],
            'post' => [],
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            'cookies' => $cookies,
        ]);
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

    public function testCustomerStaffIndexReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login as E2E customer must succeed (seed user e2e_customer/password123)');
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/index.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(403, $res['code']);
        $this->assertStringContainsString('Staff or admin only', $res['body']);
    }

    public function testCustomerAdminUsersReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login as E2E customer must succeed (seed user e2e_customer/password123)');
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/users.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(403, $res['code']);
        $this->assertStringContainsString('Admin only', $res['body']);
    }

    // ---- Customer (non-admin) gets 403 on admin endpoints ----
    public function testCustomerAdminConfigReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login as E2E customer must succeed (seed user e2e_customer/password123)');
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

    public function testAdminUsersGetReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'admin/users.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Users', $res['body']);
    }

    public function testAdminStaffIndexReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/index.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Staff', $res['body']);
    }

    public function testAdminStaffStoresReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/stores.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Stores', $res['body']);
    }

    public function testAdminStaffTicketsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/tickets.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Tickets', $res['body']);
    }

    public function testAdminStaffDisputesReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/disputes.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Disputes', $res['body']);
    }

    public function testAdminStaffWarningsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/warnings.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Warnings', $res['body']);
    }

    public function testAdminStaffDepositsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/deposits.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Deposits', $res['body']);
    }

    public function testAdminStaffStatsReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/stats.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Stats', $res['body']);
    }

    public function testAdminStaffCategoriesReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        $res = self::runRequest(['method' => 'GET', 'uri' => 'staff/categories.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $this->assertSame(200, $res['code']);
        $this->assertStringContainsString('Categories', $res['body']);
    }

    // ---- Vendor: create store then POST item, GET deposits ----
    public function testVendorCreateStoreThenPostItemReturns200(): void
    {
        $cookies = self::loginAs('admin', 'admin');
        $this->assertNotEmpty($cookies);
        // Get CSRF token
        $pageRes = self::runRequest(['method' => 'GET', 'uri' => 'register.php', 'get' => [], 'post' => [], 'headers' => [], 'cookies' => $cookies]);
        $csrf = self::extractCsrfFromBody($pageRes['body'] ?? '');
        $this->assertNotSame('', $csrf, 'Should extract CSRF token');
        $storeName = 'e2ev' . substr(bin2hex(random_bytes(4)), 0, 6);
        $createRes = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/stores.php',
            'get' => [],
            'post' => ['storename' => $storeName, 'description' => 'Vendor E2E', 'vendorship_agree' => '1', 'csrf_token' => $csrf],
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
            'post' => ['name' => 'E2E Item', 'description' => 'Test item', 'store_uuid' => $storeUuid, 'csrf_token' => $csrf],
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
