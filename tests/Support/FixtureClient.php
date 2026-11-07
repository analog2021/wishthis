<?php

declare(strict_types=1);

namespace wishthis\Tests\Support;

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
