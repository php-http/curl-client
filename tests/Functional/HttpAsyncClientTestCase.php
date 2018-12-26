<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Tests\HttpAsyncClientTest;

/**
 * Base class for asynchronous functional client tests.
 */
abstract class HttpAsyncClientTestCase extends HttpAsyncClientTest
{
    /**
     * TODO Summary.
     *
     * @param string $method  HTTP method.
     * @param string $uri     Request URI.
     * @param array  $headers HTTP headers.
     * @param string $body    Request body.
     *
     * @dataProvider requestProvider
     */
    public function testAsyncSendRequest($method, $uri, array $headers, $body): void
    {
        if ($body !== null && in_array($method, ['GET', 'HEAD', 'TRACE'], true)) {
            self::markTestSkipped('cURL can not send body using '.$method);
        }
        parent::testAsyncSendRequest(
            $method,
            $uri,
            $headers,
            $body
        );
    }

    /**
     * TODO Summary.
     *
     * @param array  $uriAndOutcome   TODO ???
     * @param string $protocolVersion HTTP version.
     * @param array  $headers         HTTP headers.
     * @param string $body            Request body.
     *
     * @dataProvider requestWithOutcomeProvider
     */
    public function testSendAsyncRequestWithOutcome(
        $uriAndOutcome,
        $protocolVersion,
        array $headers,
        $body
    ): void {
        if ( $body !== null) {
            self::markTestSkipped('cURL can not send body using GET');
        }
        parent::testSendAsyncRequestWithOutcome(
            $uriAndOutcome,
            $protocolVersion,
            $headers,
            $body
        );
    }
}
