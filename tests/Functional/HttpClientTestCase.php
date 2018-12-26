<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Tests\HttpClientTest;
use Http\Client\Tests\PHPUnitUtility;
use Psr\Http\Message\StreamInterface;

/**
 * Base class for functional client tests.
 */
abstract class HttpClientTestCase extends HttpClientTest
{
    /**
     * Temporary files names created by test.
     *
     * @var string[]
     */
    protected $tmpFiles = [];

    /**
     * Test sending large files.
     */
    public function testSendLargeFile(): void
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
     * TODO Summary.
     *
     * @param string $method  HTTP method.
     * @param string $uri     Request URI.
     * @param array  $headers HTTP headers.
     * @param string $body    Request body.
     *
     * @dataProvider requestProvider
     */
    public function testSendRequest($method, $uri, array $headers, $body): void
    {
        if ($body !== null && in_array($method, ['GET', 'HEAD', 'TRACE'], true)) {
            self::markTestSkipped('cURL can not send body using '.$method);
        }
        parent::testSendRequest(
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
    public function testSendRequestWithOutcome(
        $uriAndOutcome,
        $protocolVersion,
        array $headers,
        $body
    ): void {
        if ($body !== null) {
            self::markTestSkipped('cURL can not send body using GET');
        }
        parent::testSendRequestWithOutcome(
            $uriAndOutcome,
            $protocolVersion,
            $headers,
            $body
        );
    }

    /**
     * Create stream from file.
     *
     * @param string $filename
     *
     * @return StreamInterface
     */
    abstract protected function createFileStream($filename): StreamInterface;

    /**
     * Create temporary file.
     *
     * @return string Filename
     */
    protected function createTempFile(): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'tests');
        $this->tmpFiles[] = $filename;

        return $filename;
    }

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
