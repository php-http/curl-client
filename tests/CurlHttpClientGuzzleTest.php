<?php
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Client\Utils\MessageFactory\GuzzleMessageFactory;
use Http\Client\Utils\StreamFactory\GuzzleStreamFactory;
use Http\Curl\CurlHttpClient;

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
