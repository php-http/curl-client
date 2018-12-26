<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Curl\Client;
use Http\Client\HttpAsyncClient;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\StreamFactory;

/**
 * Testing asynchronous requests with Zend Diactoros factories.
 */
class HttpAsyncClientDiactorosTest extends HttpAsyncClientTestCase
{
    /**
     * Create asynchronous HTTP client for tests.
     *
     * @return HttpAsyncClient
     */
    protected function createHttpAsyncClient(): HttpAsyncClient
    {
        return new Client(new ResponseFactory(), new StreamFactory());
    }
}
