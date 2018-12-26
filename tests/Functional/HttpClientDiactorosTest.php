<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\Stream;
use Zend\Diactoros\StreamFactory;

/**
 * Testing synchronous requests with Zend Diactoros factories.
 */
class HttpClientDiactorosTest extends HttpClientTestCase
{
    /**
     * Create stream from file.
     *
     * @param string $filename
     *
     * @return StreamInterface
     */
    protected function createFileStream($filename): StreamInterface
    {
        return new Stream($filename);
    }

    /**
     * Create HTTP client for tests.
     *
     * @return HttpClient
     */
    protected function createHttpAdapter(): HttpClient
    {
        return new Client(new ResponseFactory(), new StreamFactory());
    }
}
