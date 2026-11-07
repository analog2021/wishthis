<?php

declare(strict_types=1);

namespace wishthis\Tests\Support;

use Embed\Http\UrlPolicy;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Serves fixture files instead of making real requests. A fixture is either a
 * file name, `[file, status, headers]` or an exception to throw.
 */
final class FixtureClient implements ClientInterface
{
    public array $requested = [];

    public function __construct(private array $fixtures)
    {
    }

    /**
     * Lets the embed crawler request the fixture hosts.
     *
     * Since embed/embed 4.6 the crawler resolves every host and refuses
     * non-public addresses before calling the client, so the made-up fixture
     * hosts (e.g. shop.example) would fail with "Could not resolve host".
     * Resolve them all to a public address instead; no
     * connection is made, the fixture client answers every request.
     *
     * @param \Embed\Embed $embed
     *
     * @return \Embed\Embed
     */
    public static function allowFixtureHosts(\Embed\Embed $embed): \Embed\Embed
    {
        $embed->getCrawler()->setUrlPolicy(
            UrlPolicy::default()->withResolver(static fn (string $host): array => ['93.184.215.14'])
        );

        return $embed;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url               = (string) $request->getUri();
        $this->requested[] = $url;
        $responseFactory   = new ResponseFactory();
        $streamFactory     = new StreamFactory();

        if (!isset($this->fixtures[$url])) {
            return $responseFactory->createResponse(404);
        }

        if ($this->fixtures[$url] instanceof \Throwable) {
            throw $this->fixtures[$url];
        }

        [$file, $status, $headers] = \is_array($this->fixtures[$url]) ? $this->fixtures[$url] + [1 => 200, 2 => []] : [$this->fixtures[$url], 200, []];

        $response = $responseFactory
        ->createResponse($status)
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        ->withBody($streamFactory->createStreamFromFile(__DIR__ . '/../fixtures/embed/' . $file));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
