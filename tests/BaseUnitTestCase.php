<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests;

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
     * @param mixed $uri
     *
     * @return RequestInterface
     */
    protected function createRequest(string $method, $uri): RequestInterface
    {
        return MessageFactoryDiscovery::find()->createRequest($method, $uri);
    }

    /**
     * Create new response.
     *
     * @return ResponseInterface
     */
    protected function createResponse(): ResponseInterface
    {
        return MessageFactoryDiscovery::find()->createResponse();
    }
}
