<?php
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Curl\CurlHttpClient;
use Http\Discovery\MessageFactory\GuzzleMessageFactory;
use Http\Discovery\StreamFactory\GuzzleStreamFactory;

/**
 * Tests for Http\Curl\CurlHttpClient
 */
class CurlHttpClientGuzzleTest extends CurlHttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new CurlHttpClient(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }
}
