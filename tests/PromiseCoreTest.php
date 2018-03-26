<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\PromiseCore;
use Http\Client\Curl\ResponseBuilder;
use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests for Http\Client\Curl\PromiseCore.
 *
 * @covers \Http\Client\Curl\PromiseCore
 */
class PromiseCoreTest extends BaseUnitTestCase
{
    /**
     * Testing if handle is not a resource.
     */
    public function testHandleIsNotAResource()
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            'Parameter $handle expected to be a cURL resource, NULL given'
        );

        new PromiseCore(
            $this->createRequest('GET', '/'),
            null,
            new ResponseBuilder($this->createResponse())
        );
    }

    /**
     * Testing if handle is not a cURL resource.
     */
    public function testHandleIsNotACurlResource()
    {
        $this->setExpectedException(
            \InvalidArgumentException::class,
            'Parameter $handle expected to be a cURL resource, stream resource given'
        );

        new PromiseCore(
            $this->createRequest('GET', '/'),
            fopen('php://memory', 'r+b'),
            new ResponseBuilder($this->createResponse())
        );
    }

    /**
     * Test on fulfill actions.
     */
    public function testOnFulfill()
    {
        $request = $this->createRequest('GET', '/');
        $this->handle = curl_init();

        $core = new PromiseCore(
            $request,
            $this->handle,
            new ResponseBuilder($this->createResponse())
        );
        static::assertSame($request, $core->getRequest());
        static::assertSame($this->handle, $core->getHandle());

        $core->addOnFulfilled(
            function (ResponseInterface $response) {
                return $response->withAddedHeader('X-Test', 'foo');
            }
        );

        $core->fulfill();
        static::assertEquals(Promise::FULFILLED, $core->getState());
        static::assertInstanceOf(ResponseInterface::class, $core->getResponse());
        static::assertEquals('foo', $core->getResponse()->getHeaderLine('X-Test'));

        $core->addOnFulfilled(
            function (ResponseInterface $response) {
                return $response->withAddedHeader('X-Test', 'bar');
            }
        );
        static::assertEquals('foo, bar', $core->getResponse()->getHeaderLine('X-Test'));
    }

    /**
     * Test on reject actions.
     */
    public function testOnReject()
    {
        $request = $this->createRequest('GET', '/');
        $this->handle = curl_init();

        $core = new PromiseCore(
            $request,
            $this->handle,
            new ResponseBuilder($this->createResponse())
        );
        $core->addOnRejected(
            function (RequestException $exception) {
                throw new RequestException('Foo', $exception->getRequest(), $exception);
            }
        );

        $exception = new RequestException('Error', $request);
        $core->reject($exception);
        static::assertEquals(Promise::REJECTED, $core->getState());
        static::assertInstanceOf(Exception::class, $core->getException());
        static::assertEquals('Foo', $core->getException()->getMessage());

        $core->addOnRejected(
            function (RequestException $exception) {
                return new RequestException('Bar', $exception->getRequest(), $exception);
            }
        );
        static::assertEquals('Bar', $core->getException()->getMessage());
    }

    /**
     * «onReject» callback can throw exception.
     *
     * @see https://github.com/php-http/curl-client/issues/26
     */
    public function testIssue26()
    {
        $request = $this->createRequest('GET', '/');
        $this->handle = curl_init();

        $core = new PromiseCore(
            $request,
            $this->handle,
            new ResponseBuilder($this->createResponse())
        );
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
        static::assertEquals(Promise::REJECTED, $core->getState());
        static::assertInstanceOf(Exception::class, $core->getException());
        static::assertEquals('Bar', $core->getException()->getMessage());
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotRejected()
    {
        $request = $this->createRequest('GET', '/');
        $this->handle = curl_init();
        $core = new PromiseCore(
            $request,
            $this->handle,
            new ResponseBuilder($this->createResponse())
        );
        $core->getException();
    }
}
