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
    protected function createFileStream(string $filename): StreamInterface
    {
        return new Stream($filename);
    }

    protected function createHttpAdapter(): HttpClient
    {
        return new Client(new ResponseFactory(), new StreamFactory());
    }
}
