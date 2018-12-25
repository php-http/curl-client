<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\Client;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
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
        $client = $this->createMock(Client::class);

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();

        $headers = $createHeaders->invoke($client, $request, []);

        static::assertContains('Expect:', $headers);
    }

    /**
     * "Expect" header should be empty.
     *
     * @link https://github.com/php-http/curl-client/issues/18
     */
    public function testWithNullPostFields()
    {
        $client = $this->createMock(Client::class);

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();
        $request = $request->withHeader('content-length', '0');

        $headers = $createHeaders->invoke($client, $request, [CURLOPT_POSTFIELDS => null]);

        static::assertContains('content-length: 0', $headers);
    }

    public function testRewindStream()
    {
        $client = $this->createMock(Client::class);

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
        $client = $this->createMock(Client::class);

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

    public function testInvalidCurlOptions()
    {
        $this->expectException(InvalidOptionsException::class);
        new Client(
            $this->createMock(MessageFactory::class),
            $this->createMock(StreamFactory::class),
            [
                CURLOPT_HEADER => true, // this won't work with our client
            ]
        );
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
