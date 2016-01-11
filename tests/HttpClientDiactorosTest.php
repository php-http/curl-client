<?php
namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Client\Utils\MessageFactory\DiactorosMessageFactory;
use Http\Client\Utils\StreamFactory\DiactorosStreamFactory;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

/**
 * Tests for Http\Curl\Client
 */
class HttpClientDiactorosTest extends HttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new Client(new DiactorosMessageFactory(), new DiactorosStreamFactory());
    }
}
