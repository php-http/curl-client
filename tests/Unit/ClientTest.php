<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Unit;

use GuzzleHttp\Psr7\Utils;
use Http\Client\Curl\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Laminas\Diactoros\Request;

/**
 * @covers \Http\Client\Curl\Client
 */
class ClientTest extends TestCase
{
    /**
     * Tests throwing InvalidArgumentException when invalid cURL options passed to constructor.
     */
    public function testExceptionThrownOnInvalidCurlOptions(): void
    {
        $this->expectException(InvalidOptionsException::class);
        new Client(
            $this->createMock(ResponseFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            [
                CURLOPT_HEADER => true, // this won't work with our client
            ]
        );
    }

    /**
     * "Expect" header should be empty by default.
     *
     * @link https://github.com/php-http/curl-client/issues/18
     */
    public function testExpectHeaderIsEmpty(): void
    {
        $client = $this->createMock(Client::class);

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();

        $headers = $createHeaders->invoke($client, $request, []);

        static::assertContains('Expect:', $headers);
    }

    /**
     * "Expect" header should be empty when POST field is empty.
     *
     * @link https://github.com/php-http/curl-client/issues/18
     */
    public function testExpectHeaderIsEmpty2(): void
    {
        $client = $this->createMock(Client::class);

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();
        $request = $request->withHeader('content-length', '0');

        $headers = $createHeaders->invoke($client, $request, [CURLOPT_POSTFIELDS => '']);

        self::assertContains('content-length: 0', $headers);
    }

    public function testRewindLargeStream(): void
    {
        $client = $this->createMock(Client::class);

        $bodyOptions = new \ReflectionMethod(Client::class, 'addRequestBodyOptions');
        $bodyOptions->setAccessible(true);

        $content = 'abcdef';
        while (strlen($content) < 1024 * 1024 + 100) {
            $content .= '123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890';
        }

        $length = strlen($content);
        $body = Utils::streamFor($content);
        $body->seek(40);
        $request = new Request('http://foo.com', 'POST', $body);
        $options = $bodyOptions->invoke($client, $request, []);

        static::assertNotFalse(
            strpos($options[CURLOPT_READFUNCTION](null, null, $length), 'abcdef'), 'Steam was not rewinded'
        );
    }

    public function testRewindStream(): void
    {
        $client = $this->createMock(Client::class);

        $bodyOptions = new \ReflectionMethod(Client::class, 'addRequestBodyOptions');
        $bodyOptions->setAccessible(true);

        $body = Utils::streamFor('abcdef');
        $body->seek(3);
        $request = new Request('http://foo.com', 'POST', $body);
        $options = $bodyOptions->invoke($client, $request, []);

        static::assertEquals('abcdef', $options[CURLOPT_POSTFIELDS]);
    }
}
