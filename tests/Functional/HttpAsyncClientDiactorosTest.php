<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Curl\Client;
use Http\Client\HttpAsyncClient;
use Http\Client\Tests\HttpAsyncClientTest;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\StreamFactory;

/**
 * @covers \Http\Client\Curl\Client
 */
class HttpAsyncClientDiactorosTest extends HttpAsyncClientTest
{
    /**
     * {@inheritdoc}
     */
    protected function createHttpAsyncClient(): HttpAsyncClient
    {
        return new Client(new ResponseFactory(), new StreamFactory());
    }
}
