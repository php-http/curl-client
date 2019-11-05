<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Curl\Client;
use Http\Client\HttpAsyncClient;
use Http\Client\Tests\HttpAsyncClientTest;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;

/**
 * @covers \Http\Client\Curl\Client
 */
class HttpAsyncClientGuzzleTest extends HttpAsyncClientTest
{
    /**
     * {@inheritdoc}
     */
    protected function createHttpAsyncClient(): HttpAsyncClient
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }
}
