<?php

declare(strict_types=1);

final class TransactionsApiE2ETest extends E2ETestCase
{
    public function testGetTransactionsWithoutAuthReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/transactions.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetAuthUserWithoutKeyReturns401(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'api/auth-user.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testGetAuthUserWithInvalidKeyReturns401(): void
    {
        $res = self::runRequest([
            'method' => 'GET',
            'uri' => 'api/auth-user.php',
            'get' => [],
            'post' => [],
            'headers' => ['Authorization' => 'Bearer invalid-key-xyz'],
        ]);
        $this->assertSame(401, $res['code']);
    }

    public function testPostTransactionsWithoutSessionReturns401(): void
    {
        $res = self::runRequest(['method' => 'POST', 'uri' => 'api/transactions.php', 'get' => [], 'post' => ['package_uuid' => 'x', 'required_amount' => 0.1], 'headers' => []]);
        $this->assertSame(401, $res['code']);
    }

    public function testPostTransactionsWithSessionButWithoutCsrfReturns403(): void
    {
        $cookies = self::loginAs('e2e_customer', 'password123');
        $this->assertNotEmpty($cookies, 'Login should succeed');

        $res = self::runRequest([
            'method' => 'POST',
            'uri' => 'api/transactions.php',
            'get' => [],
            'post' => ['package_uuid' => 'fake-package', 'required_amount' => 0.1],
            'headers' => [],
            'cookies' => $cookies,
        ]);
        $this->assertSame(403, $res['code']);
        $data = json_decode($res['body'], true);
        $this->assertSame('CSRF token required', $data['error'] ?? '');
    }
}
