<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\PromiseCore;
use Http\Discovery\MessageFactoryDiscovery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for unit tests.
 */
abstract class BaseUnitTestCase extends TestCase
{
    /**
     * Test cURL handle.
     *
     * @var resource|null
     */
    protected $handle = null;

    /**
     * Tears down the fixture.
     */
    protected function tearDown()
    {
        parent::tearDown();
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
    }

    /**
     * Create new request.
     *
     * @param string $method
     * @param mixed  $uri
     *
     * @return RequestInterface
     */
    protected function createRequest($method, $uri)
    {
        return MessageFactoryDiscovery::find()->createRequest($method, $uri);
    }

    /**
     * Create new response.
     *
     * @return ResponseInterface
     */
    protected function createResponse()
    {
        return MessageFactoryDiscovery::find()->createResponse();
    }

    /**
     * Create PromiseCore mock.
     *
     * @return PromiseCore|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createPromiseCore()
    {
        $class = new \ReflectionClass(PromiseCore::class);
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as &$item) {
            $item = $item->getName();
        }
        unset($item);
        $core = $this->getMockBuilder(PromiseCore::class)->disableOriginalConstructor()
            ->setMethods($methods)->getMock();

        return $core;
    }
}
