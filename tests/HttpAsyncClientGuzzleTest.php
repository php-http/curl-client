<?php
namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Client\Utils\MessageFactory\GuzzleMessageFactory;
use Http\Client\Utils\StreamFactory\GuzzleStreamFactory;

/**
 * Tests for Http\Curl\Client
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
