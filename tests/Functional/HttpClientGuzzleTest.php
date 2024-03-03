<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Stream;
use Http\Client\Curl\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Http\Client\Curl\Client
 */
class HttpClientGuzzleTest extends HttpClientTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function createHttpAdapter(): ClientInterface
    {
        return new Client(new HttpFactory(), new HttpFactory());
    }

    /**
     * {@inheritdoc}
     */
    protected function createFileStream(string $filename): StreamInterface
    {
        return new Stream(fopen($filename, 'r'));
    }
}
