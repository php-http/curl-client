<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Functional;

use Http\Client\Tests\HttpClientTest;
use Http\Client\Tests\PHPUnitUtility;
use PHPUnit\Framework\Attributes\DataProvider;
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
    protected array $tmpFiles = [];

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

        $request = self::$requestFactory->createRequest('POST', PHPUnitUtility::getUri());
        $request = $request->withHeader('content-length', 1024 * 2048);
        $request = $request->withBody($body);

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
     * @dataProvider requestProvider
     */
    #[DataProvider('requestProvider')]
    public function testSendRequest(string $httpMethod, string $uri, array $httpHeaders, ?string $requestBody): void
    {
        if ($requestBody !== null && in_array($httpMethod, ['GET', 'HEAD', 'TRACE'], true)) {
            self::markTestSkipped('cURL can not send body using '.$httpMethod);
        }
        parent::testSendRequest(
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
    public function testSendRequestWithOutcome(
        array $uriAndOutcome,
        string $httpVersion,
        array $httpHeaders,
        ?string $requestBody
    ): void {
        if ($requestBody !== null) {
            self::markTestSkipped('cURL can not send body using GET');
        }
        parent::testSendRequestWithOutcome(
            $uriAndOutcome,
            $httpVersion,
            $httpHeaders,
            $requestBody
        );
    }

    abstract protected function createFileStream(string $filename): StreamInterface;

    /**
     * Create temporary file.
     */
    protected function createTempFile(): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'tests');
        $this->tmpFiles[] = $filename;

        return $filename;
    }

    /**
     * Delete files created with createTempFile
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tmpFiles as $filename) {
            @unlink($filename);
        }
    }
}
