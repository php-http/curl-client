<?php
namespace Http\Curl\Tests;

use Http\Client\HttpClient;
use Http\Client\Utils\MessageFactory\DiactorosMessageFactory;
use Http\Client\Utils\StreamFactory\DiactorosStreamFactory;
use Http\Curl\CurlHttpClient;
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
        return new CurlHttpClient(new DiactorosMessageFactory(), new DiactorosStreamFactory());
    }
}
