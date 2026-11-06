<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\TestCase;
use wishthis\Cache\Embed;
use wishthis\Wish;

require_once __DIR__ . '/../vendor/autoload.php';

if (!\defined('ROOT')) {
    \define('ROOT', \dirname(__DIR__));
}

require_once ROOT . '/src/classes/wishthis/Cache/Cache.php';
require_once ROOT . '/src/classes/wishthis/Cache/Embed.php';
require_once ROOT . '/src/classes/wishthis/Wish.php';

final class WishDetailsTest extends TestCase
{
    private static function info(array $fields): \stdClass
    {
        return (object) ($fields + ['url' => 'https://shop.example/product']);
    }

    private static function imageResponse(int $code): callable
    {
        return fn (string $url): int => $code;
    }

    public function testEverythingFound(): void
    {
        $details = Wish::getDetailsFromInfo(
            self::info(
                [
                    'title'       => ' Hoodie ',
                    'description' => 'A warm hoodie.',
                    'image'       => 'https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SX300_.jpg',
                    'price'       => 49.5,
                    'currency'    => 'EUR',
                    'problem'     => null,
                ]
            ),
            'EUR',
            self::imageResponse(200)
        );

        $this->assertSame('Hoodie', $details['title']);
        $this->assertSame('A warm hoodie.', $details['description']);
        $this->assertSame('https://m.media-amazon.com/images/I/61dNySbeVWL._AC_SL1500_.jpg', $details['image'], 'The full size image is used.');
        $this->assertSame(49.5, $details['price']);
        $this->assertSame([], $details['missing']);
        $this->assertNull($details['problem']);
    }

    public function testBlockedPage(): void
    {
        $details = Wish::getDetailsFromInfo(
            self::info(['blocked' => true, 'problem' => Embed::PROBLEM_BLOCKED]),
            'EUR',
            self::imageResponse(200)
        );

        $this->assertSame(Embed::PROBLEM_BLOCKED, $details['problem']);
        $this->assertSame(['title', 'image', 'description', 'price'], $details['missing']);
    }

    public function testUnreachableImageIsMissing(): void
    {
        $details = Wish::getDetailsFromInfo(
            self::info(['title' => 'Hoodie', 'image' => 'https://shop.example/hoodie.jpg']),
            'EUR',
            self::imageResponse(403)
        );

        $this->assertSame('', $details['image']);
        $this->assertContains('image', $details['missing']);
    }

    public function testPriceInOtherCurrencyIsNotUsed(): void
    {
        $details = Wish::getDetailsFromInfo(
            self::info(['title' => 'Hoodie', 'price' => 49.5, 'currency' => 'USD']),
            'EUR',
            self::imageResponse(200)
        );

        $this->assertNull($details['price']);
        $this->assertSame('USD', $details['priceCurrency']);
        $this->assertContains('price', $details['missing']);
    }

    public function testInfoCachedBeforeProblemsWereRecorded(): void
    {
        $details = Wish::getDetailsFromInfo(self::info(['title' => 'Hoodie']), 'EUR', self::imageResponse(200));

        $this->assertNull($details['problem']);
        $this->assertSame(['image', 'description', 'price'], $details['missing']);
    }

    public function testLongTitleIsShortened(): void
    {
        $details = Wish::getDetailsFromInfo(self::info(['title' => \str_repeat('a', 200)]), 'EUR', self::imageResponse(200));

        $this->assertSame(Wish::TITLE_MAX_LENGTH, \mb_strlen($details['title']));
    }
}
