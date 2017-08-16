<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\DiactorosMessageFactory;
use Http\Message\StreamFactory\DiactorosStreamFactory;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Stream;

/**
 * Tests for Http\Client\Curl\Client.
 */
class HttpClientDiactorosTest extends HttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new Client(new DiactorosMessageFactory(), new DiactorosStreamFactory());
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
        return new Stream($filename);
    }
}
