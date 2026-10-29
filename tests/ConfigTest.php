<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use wishthis\Config;

/**
 * Every test runs in its own process, since constants defined by a
 * configuration can't be undefined again.
 */
final class ConfigTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/config';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../src/classes/wishthis/Config.php';
    }

    #[RunInSeparateProcess]
    public function testConfigFromVersion123DefaultsToMysql(): void
    {
        $config = new Config(self::FIXTURES . '/config-v1.2.3.php');

        $this->assertTrue($config->load());
        $this->assertTrue(\defined('DATABASE_ENGINE'));
        $this->assertSame('mysql', \constant('DATABASE_ENGINE'));
        $this->assertSame('db.example', \constant('DATABASE_HOST'));
        $this->assertSame('wishthis_test', \constant('DATABASE_NAME'));
        $this->assertSame('wishthis_user', \constant('DATABASE_USER'));
        $this->assertSame('not-a-real-password', \constant('DATABASE_PASSWORD'));
    }

    #[RunInSeparateProcess]
    public function testConfiguredEngineIsKept(): void
    {
        $config = new Config(self::FIXTURES . '/config-sqlite.php');

        $this->assertTrue($config->load());
        $this->assertSame('sqlite', \constant('DATABASE_ENGINE'));
    }

    #[RunInSeparateProcess]
    public function testConfigWithoutDatabaseGetsNoEngine(): void
    {
        $config = new Config(self::FIXTURES . '/config-incomplete.php');

        $this->assertTrue($config->load());
        $this->assertFalse(\defined('DATABASE_ENGINE'));
    }

    #[RunInSeparateProcess]
    public function testMissingConfig(): void
    {
        $config = new Config(self::FIXTURES . '/does-not-exist.php');

        $this->assertFalse($config->exists());
        $this->assertFalse($config->load());
        $this->assertFalse(\defined('DATABASE_ENGINE'));
    }
}
