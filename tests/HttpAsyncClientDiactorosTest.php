<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\DiactorosMessageFactory;
use Http\Message\StreamFactory\DiactorosStreamFactory;

/**
 * Tests for Http\Client\Curl\Client.
 */
class HttpAsyncClientDiactorosTest extends HttpAsyncClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAsyncClient()
    {
        return new Client(new DiactorosMessageFactory(), new DiactorosStreamFactory());
    }
}
