<?php
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Curl\CurlHttpClient;
use Http\Curl\Tests\StreamFactory\GuzzleStreamFactory;
use Http\Discovery\MessageFactory\GuzzleFactory;

/**
 * Tests for Http\Curl\CurlHttpClient
 */
class CurlHttpAsyncClientGuzzleTest extends CurlHttpAsyncClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAsyncClient()
    {
        return new CurlHttpClient(new GuzzleFactory(), new GuzzleStreamFactory());
    }
}
