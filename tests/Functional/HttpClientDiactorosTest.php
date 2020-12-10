<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;


use Http\Client\Curl\Client;
use Psr\Http\Client\ClientInterface;
use Http\Client\HttpClient;
use Psr\Http\Message\StreamInterface;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\StreamFactory;

/**
 * Testing synchronous requests with Laminas Diactoros factories.
 */
class HttpClientDiactorosTest extends HttpClientTestCase
{
    protected function createFileStream(string $filename): StreamInterface
    {
        return new Stream($filename);
    }

    protected function createHttpAdapter(): ClientInterface
    {
        return new Client(new ResponseFactory(), new StreamFactory());
    }
}
