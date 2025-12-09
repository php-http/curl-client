<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Tests\HttpAsyncClientTest;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Base class for asynchronous functional client tests.
 */
abstract class HttpAsyncClientTestCase extends HttpAsyncClientTest
{
    /**
     * @dataProvider requestProvider
     */
    #[DataProvider('requestProvider')]
    public function testAsyncSendRequest(string $httpMethod, string $uri, array $httpHeaders, ?string $requestBody): void
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
     * @dataProvider requestWithOutcomeProvider
     */
    #[DataProvider('requestWithOutcomeProvider')]
    public function testSendAsyncRequestWithOutcome(
        array $uriAndOutcome,
        string $httpVersion,
        array $httpHeaders,
        ?string $requestBody
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
