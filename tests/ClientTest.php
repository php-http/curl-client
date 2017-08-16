<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Request;

/**
 * Tests for Http\Client\Curl\Client.
 *
 * @covers \Http\Client\Curl\Client
 */
class ClientTest extends TestCase
{
    /**
     * "Expect" header should be empty.
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

    public function testRewindStream()
    {
        $client = $this->getMockBuilder(Client::class)->disableOriginalConstructor()
            ->setMethods(['__none__'])->getMock();

        $bodyOptions = new \ReflectionMethod(Client::class, 'addRequestBodyOptions');
        $bodyOptions->setAccessible(true);

        $body = \GuzzleHttp\Psr7\stream_for('abcdef');
        $body->seek(3);
        $request = new Request('http://foo.com', 'POST', $body);
        $options = $bodyOptions->invoke($client, $request, []);

        static::assertEquals('abcdef', $options[CURLOPT_POSTFIELDS]);
    }

    public function testRewindLargeStream()
    {
        $client = $this->getMockBuilder(Client::class)->disableOriginalConstructor()
            ->setMethods(['__none__'])->getMock();

        $bodyOptions = new \ReflectionMethod(Client::class, 'addRequestBodyOptions');
        $bodyOptions->setAccessible(true);

        $content = 'abcdef';
        while (strlen($content) < 1024 * 1024 + 100) {
            $content .= '123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890';
        }

        $length = strlen($content);
        $body = \GuzzleHttp\Psr7\stream_for($content);
        $body->seek(40);
        $request = new Request('http://foo.com', 'POST', $body);
        $options = $bodyOptions->invoke($client, $request, []);

        static::assertTrue(false !== strstr($options[CURLOPT_READFUNCTION](null, null, $length), 'abcdef'), 'Steam was not rewinded');
    }

    /**
     * Discovery should be used if no factory given.
     */
    public function testFactoryDiscovery()
    {
        $client = new Client();

        static::assertInstanceOf(Client::class, $client);
    }
}
