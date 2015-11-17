<?php
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Curl\CurlHttpClient;
use Http\Curl\Tests\StreamFactory\DiactorosStreamFactory;
use Http\Discovery\MessageFactory\DiactorosFactory;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

/**
 * Tests for Http\Curl\CurlHttpClient
 */
class CurlHttpAsyncClientDiactorosTest extends CurlHttpAsyncClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAsyncClient()
    {
        return new CurlHttpClient(new DiactorosFactory(), new DiactorosStreamFactory());
    }
}
