<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use wishthis\Cache\Embed;

require_once __DIR__ . '/../vendor/autoload.php';

if (!\defined('ROOT')) {
    \define('ROOT', \dirname(__DIR__));
}

\spl_autoload_register(
    function (string $class) {
        if (\str_starts_with($class, 'wishthis\\') && !\str_starts_with($class, 'wishthis\\Tests\\')) {
            require ROOT . '/src/classes/' . \str_replace('\\', '/', $class) . '.php';
        }
    }
);

/**
 * Serves fixture files instead of making real requests.
 */
final class FixtureClient implements ClientInterface
{
    public array $requested = [];

    public function __construct(private array $fixtures)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url                 = (string) $request->getUri();
        $this->requested[]   = $url;
        $responseFactory     = new ResponseFactory();
        $streamFactory       = new StreamFactory();

        if (!isset($this->fixtures[$url])) {
            return $responseFactory->createResponse(404);
        }

        return $responseFactory
        ->createResponse(200)
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        ->withBody($streamFactory->createStreamFromFile(__DIR__ . '/fixtures/embed/' . $this->fixtures[$url]));
    }
}

/**
 * The embed cache, writing to a temporary directory and using the fixture
 * client.
 */
final class FixtureEmbedCache extends Embed
{
    public static ?ClientInterface $client = null;

    public static function createEmbed(?ClientInterface $client = null): \Embed\Embed
    {
        return parent::createEmbed(self::$client);
    }

    public function __construct(string $url, string $directory)
    {
        parent::__construct($url);

        $this->directory = $directory;
    }
}

final class EmbedAutofillTest extends TestCase
{
    private const FIXTURES = [
        'https://www.amazon.com/dp/PRODUCT'       => 'amazon-product.html',
        'https://www.amazon.de/dp/BOOK'           => 'amazon-book.html',
        'https://www.amazon.com/dp/BLOCKED'       => 'amazon-blocked.html',
        'https://shop.example/foam-roller'        => 'shop-jsonld.html',
        'https://shop.example/desk-lamp'          => 'shop-microdata.html',
    ];

    private FixtureClient $client;
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->client                = new FixtureClient(self::FIXTURES);
        FixtureEmbedCache::$client   = $this->client;
        $this->cacheDirectory        = \sys_get_temp_dir() . '/wishthis-embed-test-' . \bin2hex(\random_bytes(4)) . '/embed';
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->cacheDirectory . '/*') ?: [] as $file) {
            \unlink($file);
        }

        if (\is_dir($this->cacheDirectory)) {
            \rmdir($this->cacheDirectory);
            \rmdir(\dirname($this->cacheDirectory));
        }
    }

    private function extract(string $url): \Embed\Extractor
    {
        return Embed::createEmbed($this->client)->get($url);
    }

    public function testAmazonProductPage(): void
    {
        $info = $this->extract('https://www.amazon.com/dp/PRODUCT');

        $this->assertSame('Rice Cooker 3-Cup, Stainless Steel', $info->title);
        $this->assertSame(
            "Compact: cooks as little as 1/2 a cup of rice\nSteel cut oatmeal setting\nBrown rice setting\nRetractable power cord\nKeep warm function",
            $info->description
        );
        $this->assertSame('https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SL1500_.jpg', (string) $info->image);
        $this->assertSame(189.95, $info->getPrice());
        $this->assertSame('USD', $info->getCurrency());
        $this->assertFalse($info->isBlocked());
    }

    public function testAmazonFallbacks(): void
    {
        $info = $this->extract('https://www.amazon.de/dp/BOOK');

        $this->assertSame('Ein Buch: Mit Untertitel', $info->title, 'Prefix and category are removed from the page title.');
        $this->assertSame('Eine Beschreibung des Buches.', $info->description);
        $this->assertSame('https://m.media-amazon.com/images/I/51book._SY522_.jpg', (string) $info->image, 'The largest image variant is used.');
        $this->assertNull($info->getPrice());
        $this->assertSame('EUR', $info->getCurrency());
    }

    public function testAmazonBotCheckIsDetected(): void
    {
        $this->assertTrue($this->extract('https://www.amazon.com/dp/BLOCKED')->isBlocked());
    }

    public function testLinkedDataImageAsString(): void
    {
        $info = $this->extract('https://shop.example/foam-roller');

        $this->assertSame('https://shop.example/img/roller-large.jpg', (string) $info->image);
    }

    public function testMicrodataImage(): void
    {
        $info = $this->extract('https://shop.example/desk-lamp');

        $this->assertSame('https://shop.example/images/lamp.jpg', (string) $info->image);
    }

    public function testCacheStoresAmazonDetails(): void
    {
        $cache = new FixtureEmbedCache('https://www.amazon.com/dp/PRODUCT', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertSame('Rice Cooker 3-Cup, Stainless Steel', $info->title);
        $this->assertSame('https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SL1500_.jpg', $info->image);
        $this->assertSame(189.95, $info->price);
        $this->assertSame('USD', $info->currency);
        $this->assertFalse($info->blocked);
        $this->assertTrue($cache->exists());
        $this->assertSame(['https://www.amazon.com/dp/PRODUCT'], $this->client->requested, 'The page is requested exactly once.');

        /** A fresh cache is reused without a new request. */
        $cache->get($cache->generateCache());

        $this->assertCount(1, $this->client->requested);
    }

    public function testCacheIgnoresBotCheck(): void
    {
        $cache = new FixtureEmbedCache('https://www.amazon.com/dp/BLOCKED', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertTrue($info->blocked);
        $this->assertSame('https://www.amazon.com/dp/BLOCKED', $info->url);
        $this->assertObjectNotHasProperty('title', $info);
        $this->assertFalse($cache->exists(), 'The bot check page must not be cached.');
    }

    public function testCacheDropsDescriptionRepeatingTitle(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/desk-lamp', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertSame('Desk Lamp', $info->title);
        $this->assertSame('', $info->description);
    }

    public function testCacheIgnoresEmptyResults(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/missing', $this->cacheDirectory);

        $cache->get(true);

        $this->assertFalse($cache->exists());
    }
}
