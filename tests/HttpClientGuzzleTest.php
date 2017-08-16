<?php

namespace Http\Client\Curl\Tests;

use GuzzleHttp\Psr7\Stream;
use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;
use Psr\Http\Message\StreamInterface;

/**
 * Tests for Http\Client\Curl\Client.
 */
class HttpClientGuzzleTest extends HttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }

    /**
     * Create stream from file.
     *
     * @param string $filename
     *
     * @return StreamInterface
     */
    protected function createFileStream($filename)
    {
        return new Stream(fopen($filename, 'r'));
    }
}
