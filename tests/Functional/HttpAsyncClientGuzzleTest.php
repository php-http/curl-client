<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Curl\Client;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;

/**
 * Tests for Http\Client\Curl\Client.
 */
class HttpAsyncClientGuzzleTest extends HttpAsyncClientTestCase
{
    /**
     * Create asynchronious HTTP client for tests.
     *
     * @return HttpAsyncClient
     */
    protected function createHttpAsyncClient(): HttpAsyncClient
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }
}
