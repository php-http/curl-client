<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Tests\HttpClientTest;
use Http\Client\Tests\PHPUnitUtility;
use Psr\Http\Message\StreamInterface;

/**
 * Base class for client integration tests.
 */
abstract class HttpClientTestCase extends HttpClientTest
{
    /**
     * Temporary file name created by test.
     *
     * @var string[]
     */
    protected $tmpFiles = [];

    /**
     * @dataProvider requestProvider
     * @group        integration
     */
    public function testSendRequest($method, $uri, array $headers, $body)
    {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run under HHVM');
        }
        if (null !== $body && in_array($method, ['GET', 'HEAD', 'TRACE'], true)) {
            static::markTestSkipped('cURL can not send body using '.$method);
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

    /**
     * Test sending large files.
     */
    public function testSendLargeFile()
    {
        $filename = $this->createTempFile();
        $fd = fopen($filename, 'ab');
        $buffer = str_repeat('x', 1024);
        for ($i = 0; $i < 2048; ++$i) {
            fwrite($fd, $buffer);
        }
        fclose($fd);
        $body = $this->createFileStream($filename);

        $request = self::$messageFactory->createRequest(
            'POST',
            PHPUnitUtility::getUri(),
            ['content-length' => 1024 * 2048],
            $body
        );

        $response = $this->httpAdapter->sendRequest($request);
        $this->assertResponse(
            $response,
            [
                'body' => 'Ok',
            ]
        );

        $request = $this->getRequest();
        self::assertArrayHasKey('HTTP_CONTENT_LENGTH', $request['SERVER']);
        self::assertEquals($body->getSize(), $request['SERVER']['HTTP_CONTENT_LENGTH']);
    }

    /**
     * Create temp file.
     *
     * @return string Filename
     */
    protected function createTempFile()
    {
        $filename = tempnam(sys_get_temp_dir(), 'tests');
        $this->tmpFiles[] = $filename;

        return $filename;
    }

    /**
     * Create stream from file.
     *
     * @param string $filename
     *
     * @return StreamInterface
     */
    abstract protected function createFileStream($filename);

    /**
     * Tears down the fixture.
     */
    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->tmpFiles as $filename) {
            @unlink($filename);
        }
    }
}
