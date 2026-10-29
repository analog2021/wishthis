<?php

declare(strict_types=1);

namespace wishthis\Tests\Api;

use PHPUnit\Framework\TestCase;
use wishthis\URL;

final class PrettyUrlTest extends TestCase
{
    public function testPrettyUrl(): void
    {
        if (!\defined('ROOT')) {
            \define('ROOT', \dirname(__DIR__));
        }

        $requestUris = [
            '//api/database-test',
            '/api/database-test',
            'api/database-test',
            'http://wishthis.online.localhost/index.php/api/database-test',
            'http://wishthis.online.localhost/index.php//api/database-test',
        ];

        require_once __DIR__ . '/../src/classes/wishthis/URL.php';

        $expected_GET = [
            'page'   => 'api',
            'module' => 'database-test',
        ];

        foreach ($requestUris as $requestUri) {
            $url = new URL($requestUri);

            $this->assertSame($expected_GET, $_GET, $requestUri);
        }
    }

    public function testSpoilersUrl(): void
    {
        if (!\defined('ROOT')) {
            \define('ROOT', \dirname(__DIR__));
        }

        require_once __DIR__ . '/../src/classes/wishthis/URL.php';

        $requestUris = [
            '/spoilers/5',
            '/index.php?page=spoilers&wishlist=5',
        ];

        $expected_GET = [
            'page'     => 'spoilers',
            'wishlist' => '5',
        ];

        foreach ($requestUris as $requestUri) {
            $url = new URL($requestUri);

            $this->assertSame($expected_GET, $_GET, $requestUri);
            $this->assertSame('/spoilers/5', $url->getPretty(), $requestUri);
        }

        /** Must not be confused with "My lists", which also has one numeric parameter. */
        $url = new URL('/index.php?page=wishlists&id=5');

        $this->assertSame('/wishlists/5', $url->getPretty());
    }
}
