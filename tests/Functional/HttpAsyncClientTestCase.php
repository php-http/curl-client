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
     * {@inheritdoc}
     *
     * @dataProvider requestProvider
     */
    public function testAsyncSendRequest($httpMethod, $uri, array $httpHeaders, $requestBody): void
    {
        if ($requestBody !== null && in_array($httpMethod, ['GET', 'HEAD', 'TRACE'], true)) {
            self::markTestSkipped('cURL can not send body using '.$httpMethod);
        }
        parent::testAsyncSendRequest(
            $httpMethod,
            $uri,
            $httpHeaders,
            $requestBody
        );
    }

    /**
     * {@inheritdoc}
     *
     * @dataProvider requestWithOutcomeProvider
     */
    public function testSendAsyncRequestWithOutcome(
        $uriAndOutcome,
        $httpVersion,
        array $httpHeaders,
        $requestBody
    ): void {
        if ( $requestBody !== null) {
            self::markTestSkipped('cURL can not send body using GET');
        }
        parent::testSendAsyncRequestWithOutcome(
            $uriAndOutcome,
            $httpVersion,
            $httpHeaders,
            $requestBody
        );
    }
}
