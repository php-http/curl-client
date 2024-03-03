<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use GuzzleHttp\Psr7\HttpFactory;
use Http\Client\Curl\Client;
use Http\Client\HttpAsyncClient;

/**
 * @covers \Http\Client\Curl\Client
 */
class HttpAsyncClientGuzzleTest extends HttpAsyncClientTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function createHttpAsyncClient(): HttpAsyncClient
    {
        return new Client(new HttpFactory(), new HttpFactory());
    }
}
