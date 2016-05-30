<?php
namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Zend\Diactoros\Request;

/**
 * Tests for Http\Client\Curl\Client
 *
 * @covers Http\Client\Curl\Client
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * "Expect" header should be empty
     *
     * @link https://github.com/php-http/curl-client/issues/18
     */
    public function testExpectHeader()
    {
        $client = $this->getMockBuilder(Client::class)->disableOriginalConstructor()
            ->setMethods(['__none__'])->getMock();

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();

        $headers = $createHeaders->invoke($client, $request, []);

        static::assertContains('Expect:', $headers);
    }

    /**
     * Discovery should be used if no factory given.
     */
    public function testFactoryDiscovery()
    {
        $client = new Client;

        static::assertInstanceOf(Client::class, $client);
    }
}
