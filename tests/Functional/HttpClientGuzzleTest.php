<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use GuzzleHttp\Psr7\Stream;
use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Http\Client\Curl\Client
 */
class HttpClientGuzzleTest extends HttpClientTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function createHttpAdapter(): HttpClient
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }

    /**
     * {@inheritdoc}
     */
    protected function createFileStream(string $filename): StreamInterface
    {
        return new Stream(fopen($filename, 'r'));
    }
}
