<?php

declare(strict_types=1);

/**
 * E2E: GET health.php returns 200 OK and plain text body.
 */
final class HealthE2ETest extends E2ETestCase
{
    public function testGetHealthReturnsOk(): void
    {
        $res = self::runRequest([
            'method' => 'GET',
            'uri' => 'health.php',
            'get' => [],
            'post' => [],
            'headers' => [],
        ]);

        $this->assertSame(200, $res['code']);
        $this->assertSame('OK', trim($res['body']));
    }
}
