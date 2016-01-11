<?php
namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Client\Utils\MessageFactory\DiactorosMessageFactory;
use Http\Client\Utils\StreamFactory\DiactorosStreamFactory;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

/**
 * Tests for Http\Client\Curl\Client
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
