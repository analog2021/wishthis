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

    /**
     * Details whose detection fails, see `FailingDetector`.
     *
     * @var string[]
     */
    public static array $failingDetectors = [];

    public static function createEmbed(?ClientInterface $client = null): \Embed\Embed
    {
        $embed = FixtureClient::allowFixtureHosts(parent::createEmbed(self::$client));

        foreach (self::$failingDetectors as $name) {
            $embed->getExtractorFactory()->addDetector($name, FailingDetector::class);
        }

        return $embed;
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
