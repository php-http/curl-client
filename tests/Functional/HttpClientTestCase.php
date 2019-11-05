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

    abstract protected function createFileStream(string $filename): StreamInterface;

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
     * Delete files created with createTempFile
     */
    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->tmpFiles as $filename) {
            @unlink($filename);
        }
    }
}
