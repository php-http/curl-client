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
class CurlHttpClientDiactorosTest extends CurlHttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new CurlHttpClient(new DiactorosMessageFactory(), new DiactorosStreamFactory());
    }
}
