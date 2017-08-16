<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Tests\HttpAsyncClientTest;

/**
 * Base class for async client integration tests.
 */
abstract class HttpAsyncClientTestCase extends HttpAsyncClientTest
{
    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testAsyncSendRequest($method, $uri, array $headers, $body)
    {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run under HHVM');
        }
        if (null !== $body && in_array($method, ['GET', 'HEAD', 'TRACE'], true)) {
            static::markTestSkipped('cURL can not send body using '.$method);
        }
        parent::testAsyncSendRequest(
            $method,
            $uri,
            $headers,
            $body
        );
    }

    /**
     * @dataProvider requestWithOutcomeProvider
     * @group        integration
     */
    public function testSendAsyncRequestWithOutcome(
        $uriAndOutcome,
        $protocolVersion,
        array $headers,
        $body
    ) {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run under HHVM');
        }
        if (null !== $body) {
            static::markTestSkipped('cURL can not send body using GET');
        }
        parent::testSendAsyncRequestWithOutcome(
            $uriAndOutcome,
            $protocolVersion,
            $headers,
            $body
        );
    }

    public function testSuccessiveCallMustUseResponseInterface()
    {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run under HHVM');
        }
        parent::testSuccessiveCallMustUseResponseInterface();
    }
}
