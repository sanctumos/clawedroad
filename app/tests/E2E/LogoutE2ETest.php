<?php

declare(strict_types=1);

final class LogoutE2ETest extends E2ETestCase
{
    public function testGetLogoutRedirectsToMarketplace(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'logout.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code']);
    }
}
