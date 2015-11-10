<?php
/**
 * PSR-7 compatible cURL based HTTP client
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Curl\CurlHttpClient;
use Http\Curl\Tests\StreamFactory\GuzzleStreamFactory;
use Http\Discovery\MessageFactory\GuzzleFactory;

/**
 * Tests for Http\Curl\CurlHttpClient
 *
 * @covers Http\Curl\CurlHttpClient
 */
class CurlHttpClientGuzzleTest extends CurlHttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new CurlHttpClient(new GuzzleFactory(), new GuzzleStreamFactory());
    }
}
