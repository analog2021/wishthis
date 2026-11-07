<?php

declare(strict_types=1);

namespace wishthis\Tests\Support;

use Psr\Http\Client\ClientInterface;
use wishthis\Cache\Embed;

/**
 * The embed cache, writing to a temporary directory and using the fixture
 * client.
 */
final class FixtureEmbedCache extends Embed
{
    public static ?ClientInterface $client = null;

    public static function createEmbed(?ClientInterface $client = null): \Embed\Embed
    {
        return FixtureClient::allowFixtureHosts(parent::createEmbed(self::$client));
    }

    public function __construct(string $url, string $directory)
    {
        parent::__construct($url);

        $this->directory = $directory;
    }

    public function seed(\stdClass $info): void
    {
        $this->write($info);
    }
}
