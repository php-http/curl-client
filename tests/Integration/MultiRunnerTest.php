<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Integration;

use donatj\MockWebServer\MockWebServer;
use Http\Client\Curl\MultiRunner;
use Http\Client\Curl\PromiseCore;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Http\Client\Curl\MultiRunner.
 *
 * @covers \Http\Client\Curl\MultiRunner
 */
class MultiRunnerTest extends TestCase
{
    /**
     * Test HTTP server.
     *
     * @var MockWebServer
     */
    private static $server;

    /**
     * Prepare environment for all tests.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$server = new MockWebServer();
        self::$server->start();
    }

    /**
     * Cleanup environment after all tests.
     */
    public static function tearDownAfterClass(): void
    {
        self::$server->stop();

        parent::tearDownAfterClass();
    }

    public function testWait(): void
    {
        $runner = new MultiRunner();

        $handle = $this->createCurlHandle('/');
        $core1 = $this->createConfiguredMock(PromiseCore::class, ['getHandle' => $handle]);
        $core1
            ->expects(self::once())
            ->method('fulfill');

        $handle = $this->createCurlHandle('/');
        $core2 = $this->createConfiguredMock(PromiseCore::class, ['getHandle' => $handle]);
        $core2
            ->expects(self::once())
            ->method('fulfill');

        $runner->add($core1);
        $runner->add($core2);

        $runner->wait($core1);
        $runner->wait($core2);
    }

    /**
     * Create cURL handle with given parameters.
     *
     * @param string $url Request URL relative to server root.
     *
     * @return resource
     */
    private function createCurlHandle(string $url)
    {
        $handle = curl_init();
        self::assertNotFalse($handle);

        curl_setopt_array(
            $handle,
            [
                CURLOPT_URL => self::$server->getServerRoot() . $url
            ]
        );

        return $handle;
    }
}
