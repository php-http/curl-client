<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;

/**
 * Tests for Http\Client\Curl\Client.
 */
class HttpAsyncClientGuzzleTest extends HttpAsyncClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAsyncClient()
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }
}
