<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\TestCase;
use wishthis\Cache\Embed;
use wishthis\Tests\Support\FixtureClient;
use wishthis\Tests\Support\FixtureEmbedCache;

final class EmbedAutofillTest extends TestCase
{
    private const FIXTURES_SHOP = [
        'https://shop.example/hoodie'             => 'shop-meta-price.html',
        'https://shop.example/server-error'       => ['not-found.html', 500],
    ];

    private const FIXTURES = [
        'https://www.amazon.com/dp/PRODUCT'       => 'amazon-product.html',
        'https://www.amazon.de/dp/BOOK'           => 'amazon-book.html',
        'https://www.amazon.com/dp/BLOCKED'       => 'amazon-blocked.html',
        'https://shop.example/foam-roller'        => 'shop-jsonld.html',
        'https://shop.example/desk-lamp'          => 'shop-microdata.html',
        'https://shop.example/challenge'          => ['cloudflare-challenge.html', 403, ['cf-mitigated' => 'challenge', 'Server' => 'cloudflare']],
        'https://shop.example/too-many-requests'  => ['not-found.html', 429],
        'https://shop.example/gone'               => ['not-found.html', 404],
    ];

    private FixtureClient $client;
    private string $cacheDirectory;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        if (!\defined('ROOT')) {
            \define('ROOT', \dirname(__DIR__));
        }

        \spl_autoload_register(
            function (string $class) {
                if (\str_starts_with($class, 'wishthis\\Tests\\Support\\')) {
                    require __DIR__ . '/Support/' . \substr($class, \strlen('wishthis\\Tests\\Support\\')) . '.php';
                } elseif (\str_starts_with($class, 'wishthis\\') && !\str_starts_with($class, 'wishthis\\Tests\\')) {
                    require ROOT . '/src/classes/' . \str_replace('\\', '/', $class) . '.php';
                }
            }
        );
    }

    protected function setUp(): void
    {
        $this->client              = new FixtureClient(
            self::FIXTURES + self::FIXTURES_SHOP + [
                'https://shop.example/timeout' => new \RuntimeException('Operation timed out'),
            ]
        );
        FixtureEmbedCache::$client = $this->client;
        $this->cacheDirectory      = \sys_get_temp_dir() . '/wishthis-embed-test-' . \bin2hex(\random_bytes(4)) . '/embed';
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
        return FixtureClient::allowFixtureHosts(Embed::createEmbed($this->client))->get($url);
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
        $this->assertSame(['price' => 24.95, 'currency' => 'USD'], $info->price, 'The price is read from the linked data offers.');
    }

    public function testMicrodataImage(): void
    {
        $info = $this->extract('https://shop.example/desk-lamp');

        $this->assertSame('https://shop.example/images/lamp.jpg', (string) $info->image);
        $this->assertSame(['price' => 19.99, 'currency' => 'EUR'], $info->price, 'A decimal comma is understood.');
    }

    public function testPriceFromProductMetas(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/hoodie', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertSame('Hoodie', $info->title);
        $this->assertSame(1299.0, $info->price, 'Thousands separators are removed.');
        $this->assertSame('EUR', $info->currency);
        $this->assertNull($info->problem);
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
        $this->assertSame(Embed::PROBLEM_BLOCKED, $info->problem);
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

    public function testFullSizeImage(): void
    {
        $large = 'https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SL1500_.jpg';

        $this->assertSame($large, \wishthis\Wish::getFullSizeImage('https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SY300_SX300_QL70_ML2_.jpg'));
        $this->assertSame($large, \wishthis\Wish::getFullSizeImage('https://m.media-amazon.com/images/I/61dNySbeVWL.jpg'));
        $this->assertSame($large, \wishthis\Wish::getFullSizeImage($large));
        $this->assertSame(
            'https://images-na.ssl-images-amazon.com/images/I/41ML5EvJ+5L._AC_SL1500_.png',
            \wishthis\Wish::getFullSizeImage('https://images-na.ssl-images-amazon.com/images/I/41ML5EvJ+5L._SX38_SY50_CR,0,0,38,50_.png')
        );

        foreach (
            [
                'https://shop.example/images/I/61dNySbeVWL._AC_SY300_.jpg',
                'https://m.media-amazon.com/images/S/aplus-media/sc/1234.jpg',
                'https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SY300_.jpg?size=small',
            ] as $url
        ) {
            $this->assertSame($url, \wishthis\Wish::getFullSizeImage($url), 'Unchanged: ' . $url);
        }
    }

    public function testTitleIsNotCutInsideAnEntity(): void
    {
        $short = 'Rice Cooker&comma; 3-Cup';

        $this->assertSame($short, \wishthis\Wish::truncateTitle($short));

        /** 125 characters, then an entity which would be cut at 128. */
        $long = \str_repeat('a', 125) . '&comma; Stainless Steel';

        $this->assertSame(\str_repeat('a', 125), \wishthis\Wish::truncateTitle($long));

        /** Multibyte characters count as one character. */
        $umlauts = \str_repeat('ü', 130);

        $this->assertSame(\str_repeat('ü', 128), \wishthis\Wish::truncateTitle($umlauts));
    }

    public function testCacheIgnoresBotCheckOfOtherShops(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/challenge', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertTrue($info->blocked);
        $this->assertSame(Embed::PROBLEM_BLOCKED, $info->problem);
        $this->assertSame('https://shop.example/challenge', $info->url);
        $this->assertObjectNotHasProperty('title', $info, 'The title of the bot check page ("Just a moment...") must not be used.');
        $this->assertFalse($cache->exists(), 'The bot check page must not be cached.');
    }

    public function testRefusedRequestIsBlocked(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/too-many-requests', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertTrue($info->blocked);
        $this->assertObjectNotHasProperty('title', $info);
        $this->assertFalse($cache->exists());
    }

    public function testErrorPageIsIgnored(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/gone', $this->cacheDirectory);
        $info  = $cache->get(true);

        $this->assertFalse($info->blocked, 'An error page is not a refusal.');
        $this->assertSame(Embed::PROBLEM_NOT_FOUND, $info->problem);
        $this->assertSame('https://shop.example/gone', $info->url);
        $this->assertObjectNotHasProperty('title', $info, 'The title of the error page must not be used.');
        $this->assertObjectNotHasProperty('image', $info);
        $this->assertFalse($cache->exists());
    }

    public function testUnreachableShop(): void
    {
        /** The failed request is logged, which is expected here */
        $errorLog = \ini_set('error_log', '/dev/null');

        foreach (['https://shop.example/server-error', 'https://shop.example/timeout'] as $url) {
            $cache = new FixtureEmbedCache($url, $this->cacheDirectory);

            \ob_start();
            $info   = $cache->get(true);
            $output = \ob_get_clean();

            $this->assertSame(Embed::PROBLEM_UNREACHABLE, $info->problem, $url);
            $this->assertFalse($info->blocked, $url);
            $this->assertObjectNotHasProperty('title', $info, $url);
            $this->assertSame('', $output, 'Nothing is printed into the API response.');
            $this->assertFalse($cache->exists(), $url);
        }

        \ini_set('error_log', (string) $errorLog);
    }

    public function testCachedResultSurvivesBotCheck(): void
    {
        $url   = 'https://shop.example/challenge';
        $cache = new FixtureEmbedCache($url, $this->cacheDirectory);

        $cached        = new \stdClass();
        $cached->url   = $url;
        $cached->title = 'Babydoll Top';

        $cache->seed($cached);

        $this->assertTrue($cache->exists());

        $info = $cache->get(true);

        $this->assertSame('Babydoll Top', $info->title);
        $this->assertObjectNotHasProperty('blocked', $info);
    }

    public function testCacheIgnoresEmptyResults(): void
    {
        $cache = new FixtureEmbedCache('https://shop.example/missing', $this->cacheDirectory);

        $cache->get(true);

        $this->assertFalse($cache->exists());
    }
}
