<?php
namespace Http\Client\Curl\Tests;

use Http\Client\Tests\HttpClientTest;

/**
 * Base class for client integration tests
 */
abstract class HttpClientTestCase extends HttpClientTest
{
    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, array $headers, $body)
    {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run under HHVM');
        }
        if (null !== $body && !in_array($method, ['OPTIONS', 'POST', 'PUT'], true)) {
            static::markTestSkipped('cURL can not send body using ' . $method);
        }
        parent::testSendRequest(
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
    public function testSendRequestWithOutcome(
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
        parent::testSendRequestWithOutcome(
            $uriAndOutcome,
            $protocolVersion,
            $headers,
            $body
        );
    }
}
