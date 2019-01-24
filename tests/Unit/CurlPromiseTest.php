<?php

declare(strict_types=1);

namespace Http\Client\Curl\Tests\Unit;

use Http\Client\Curl\CurlPromise;
use Http\Client\Curl\MultiRunner;
use Http\Client\Curl\PromiseCore;
use Http\Client\Exception\TransferException;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \Http\Client\Curl\CurlPromise
 */
class CurlPromiseTest extends TestCase
{
    public function testCoreCallWaitFulfilled(): void
    {
        $core = $this->createMock(PromiseCore::class);
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()
            ->setMethods(['wait'])->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $runner->expects(self::once())->method('wait')->with($core);
        $core->expects(self::once())->method('getState')->willReturn(Promise::FULFILLED);

        $response = $this->createMock(ResponseInterface::class);
        $core->expects(self::once())->method('getResponse')->willReturn($response);
        self::assertSame($response, $promise->wait());
    }

    public function testCoreCallWaitRejected(): void
    {
        $core = $this->createMock(PromiseCore::class);
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $runner->expects(self::once())->method('wait')->with($core);
        $core->expects(self::once())->method('getState')->willReturn(Promise::REJECTED);
        $core->expects(self::once())->method('getException')->willReturn(new TransferException());

        try {
            $promise->wait();
        } catch (TransferException $exception) {
            self::assertTrue(true);
        }
    }

    public function testCoreCalls(): void
    {
        $core = $this->createMock(PromiseCore::class);
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()
            ->setMethods(['wait'])->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $onFulfill = function () {
        };
        $core->expects(self::once())->method('addOnFulfilled')->with($onFulfill);

        $onReject = function () {
        };
        $core->expects(self::once())->method('addOnRejected')->with($onReject);

        $promise->then($onFulfill, $onReject);

        $core->expects(self::once())->method('getState')->willReturn('STATE');
        self::assertEquals('STATE', $promise->getState());
    }
}
