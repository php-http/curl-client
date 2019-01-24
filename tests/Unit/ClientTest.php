<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Unit;

use Http\Client\Curl\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Zend\Diactoros\Request;

/**
 * @covers \Http\Client\Curl\Client
 */
class ClientTest extends TestCase
{
    /**
     * "Expect" header should be empty.
     *
     * @link https://github.com/php-http/curl-client/issues/18
     */
    public function testExpectHeader(): void
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
    public function testWithNullPostFields(): void
    {
        $client = $this->createMock(Client::class);

        $createHeaders = new \ReflectionMethod(Client::class, 'createHeaders');
        $createHeaders->setAccessible(true);

        $request = new Request();
        $request = $request->withHeader('content-length', '0');

        $headers = $createHeaders->invoke($client, $request, [CURLOPT_POSTFIELDS => '']);

        self::assertContains('content-length: 0', $headers);
    }

    public function testRewindStream(): void
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
        $body = \GuzzleHttp\Psr7\stream_for($content);
        $body->seek(40);
        $request = new Request('http://foo.com', 'POST', $body);
        $options = $bodyOptions->invoke($client, $request, []);

        static::assertTrue(false !== strstr($options[CURLOPT_READFUNCTION](null, null, $length), 'abcdef'), 'Steam was not rewinded');
    }

    /**
     * Tests throwing InvalidArgumentException when invalid cURL options passed to constructor.
     */
    public function testInvalidCurlOptions(): void
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
}
