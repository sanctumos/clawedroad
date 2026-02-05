<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers \Config
 */
final class ConfigTest extends TestCase
{
    private PDO $pdo;
    private Config $config;

    protected function setUp(): void
    {
        $this->pdo = Db::pdo();
        $this->config = new Config($this->pdo);
    }

    public function testGetReturnsValueWhenKeyExists(): void
    {
        $value = $this->config->get('pending_duration');
        $this->assertNotNull($value);
        $this->assertSame('24h', $value);
    }

    public function testGetReturnsNullWhenKeyMissing(): void
    {
        $value = $this->config->get('nonexistent_key_xyz');
        $this->assertNull($value);
    }

    public function testGetUsesCacheOnSecondCall(): void
    {
        $this->config->get('pending_duration');
        $ref = new ReflectionClass($this->config);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $cache = $prop->getValue($this->config);
        $this->assertArrayHasKey('pending_duration', $cache);
    }

    public function testGetFloatReturnsFloat(): void
    {
        $value = $this->config->getFloat('completion_tolerance');
        $this->assertIsFloat($value);
        $this->assertSame(0.05, $value);
    }

    public function testGetFloatReturnsDefaultWhenKeyMissing(): void
    {
        $value = $this->config->getFloat('nonexistent_float', 0.99);
        $this->assertSame(0.99, $value);
    }

    public function testSetUpdatesValue(): void
    {
        $this->config->set('test_key_xyz', 'test_value');
        $this->assertSame('test_value', $this->config->get('test_key_xyz'));
    }

    public function testSetUpdatesCache(): void
    {
        $this->config->set('test_cache_key', 'cached');
        $this->assertSame('cached', $this->config->get('test_cache_key'));
    }

    public function testSeedDefaultsIdempotent(): void
    {
        $this->config->seedDefaults();
        $this->config->seedDefaults();
        $value = $this->config->get('pending_duration');
        $this->assertSame('24h', $value);
    }

    public function testSeedDefaultsUsesCorrectSyntaxForMariaDB(): void
    {
        // Create an in-memory MySQL-like database using SQLite but with mocked driver name
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('INSERT IGNORE INTO config (key, value) VALUES (?, ?)')
            ->willReturn($stmt);

        $stmt->expects($this->atLeast(15))
            ->method('execute')
            ->willReturn(true);

        $config = new Config($pdo);
        $config->seedDefaults();
    }

    public function testSetUsesReplaceSyntaxForMariaDB(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');

        $pdo->expects($this->once())
            ->method('prepare')
            ->with('REPLACE INTO config (key, value) VALUES (?, ?)')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['test_key', 'test_value'])
            ->willReturn(true);

        $config = new Config($pdo);
        $config->set('test_key', 'test_value');
    }
}
