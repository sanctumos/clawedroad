<?php

declare(strict_types=1);

/**
 * E2E: GET index redirects to marketplace.
 */
final class IndexE2ETest extends E2ETestCase
{
    public function testGetIndexRedirectsToMarketplace(): void
    {
        $res = self::runRequest(['method' => 'GET', 'uri' => 'index.php', 'get' => [], 'post' => [], 'headers' => []]);
        $this->assertSame(302, $res['code'], 'Index must redirect (Location not available in CLI)');
    }
}
