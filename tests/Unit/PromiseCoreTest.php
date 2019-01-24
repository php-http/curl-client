<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Unit;

use Http\Client\Curl\PromiseCore;
use Http\Client\Curl\ResponseBuilder;
use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Promise\Promise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Http\Client\Curl\PromiseCore
 */
class PromiseCoreTest extends TestCase
{
    /**
     * Test cURL handle.
     *
     * @var resource|null
     */
    private $handle;

    /**
     * Testing if handle is not a cURL resource.
     */
    public function testHandleIsNotACurlResource(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter $handle expected to be a cURL resource, stream resource given');

        new PromiseCore($request, fopen('php://memory', 'r+b'), $responseBuilder);
    }

    /**
     * Testing if handle is not a resource.
     */
    public function testHandleIsNotAResource(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter $handle expected to be a cURL resource, NULL given');

        new PromiseCore($request, null, $responseBuilder);
    }

    /**
     * «onReject» callback can throw exception.
     *
     * @see https://github.com/php-http/curl-client/issues/26
     */
    public function testIssue26(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->handle = curl_init();

        $core = new PromiseCore($request, $this->handle, $responseBuilder);
        $core->addOnRejected(
            function (RequestException $exception) {
                throw new RequestException('Foo', $exception->getRequest(), $exception);
            }
        );
        $core->addOnRejected(
            function (RequestException $exception) {
                return new RequestException('Bar', $exception->getRequest(), $exception);
            }
        );

        $exception = new RequestException('Error', $request);
        $core->reject($exception);
        self::assertEquals(Promise::REJECTED, $core->getState());
        self::assertEquals('Bar', $core->getException()->getMessage());
    }

    public function testNotRejected(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->handle = curl_init();

        $core = new PromiseCore($request, $this->handle, $responseBuilder);
        $this->expectException(\LogicException::class);
        $core->getException();
    }

    public function testOnFulfill(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $stream = $this->createMock(StreamInterface::class);
        $response1 = $this->createConfiguredMock(ResponseInterface::class, ['getBody' => $stream]);
        $responseBuilder = $this->createConfiguredMock(ResponseBuilder::class, ['getResponse' => $response1]);
        $response2 = $this->createConfiguredMock(ResponseInterface::class, ['getBody' => $stream]);

        $this->handle = curl_init();

        $core = new PromiseCore($request, $this->handle, $responseBuilder);

        self::assertSame($request, $core->getRequest());
        self::assertSame($this->handle, $core->getHandle());

        $core->addOnFulfilled(
            function (ResponseInterface $response) use ($response1, $response2) {
                self::assertSame($response1, $response);

                return $response2;
            }
        );

        $core->fulfill();
        self::assertEquals(Promise::FULFILLED, $core->getState());
    }

    public function testOnReject(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->handle = curl_init();

        $core = new PromiseCore($request, $this->handle, $responseBuilder);
        $core->addOnRejected(
            function (RequestException $exception) {
                throw new RequestException('Foo', $exception->getRequest(), $exception);
            }
        );

        $exception = new RequestException('Error', $request);
        $core->reject($exception);
        self::assertEquals(Promise::REJECTED, $core->getState());
        self::assertEquals('Foo', $core->getException()->getMessage());

        $core->addOnRejected(
            function (RequestException $exception) {
                return new RequestException('Bar', $exception->getRequest(), $exception);
            }
        );
        self::assertEquals('Bar', $core->getException()->getMessage());
    }

    protected function tearDown()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }

        parent::tearDown();
    }
}
