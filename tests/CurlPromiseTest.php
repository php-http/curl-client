<?php

namespace Http\Client\Curl\Tests;

use Http\Client\Curl\CurlPromise;
use Http\Client\Curl\MultiRunner;
use Http\Client\Exception\TransferException;
use Http\Promise\Promise;

/**
 * Tests for Http\Client\Curl\CurlPromise.
 *
 * @covers \Http\Client\Curl\CurlPromise
 */
class CurlPromiseTest extends BaseUnitTestCase
{
    /**
     * Test that promise call core methods.
     */
    public function testCoreCalls()
    {
        $core = $this->createPromiseCore();
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()
            ->setMethods(['wait'])->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $onFulfill = function () {
        };
        $core->expects(static::once())->method('addOnFulfilled')->with($onFulfill);
        $onReject = function () {
        };
        $core->expects(static::once())->method('addOnRejected')->with($onReject);
        $value = $promise->then($onFulfill, $onReject);
        static::assertInstanceOf(Promise::class, $value);

        $core->expects(static::once())->method('getState')->willReturn('STATE');
        static::assertEquals('STATE', $promise->getState());
    }

    public function testCoreCallWaitFulfilled()
    {
        $core = $this->createPromiseCore();
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()
            ->setMethods(['wait'])->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $runner->expects(static::once())->method('wait')->with($core);
        $core->expects(static::once())->method('getState')->willReturn(Promise::FULFILLED);
        $core->expects(static::once())->method('getResponse')->willReturn('RESPONSE');
        static::assertEquals('RESPONSE', $promise->wait());
    }

    public function testCoreCallWaitRejected()
    {
        $core = $this->createPromiseCore();
        $runner = $this->getMockBuilder(MultiRunner::class)->disableOriginalConstructor()
            ->setMethods(['wait'])->getMock();
        /** @var MultiRunner|\PHPUnit_Framework_MockObject_MockObject $runner */
        $promise = new CurlPromise($core, $runner);

        $runner->expects(static::once())->method('wait')->with($core);
        $core->expects(static::once())->method('getState')->willReturn(Promise::REJECTED);
        $core->expects(static::once())->method('getException')->willReturn(new TransferException());

        try {
            $promise->wait();
        } catch (TransferException $exception) {
            static::assertTrue(true);
        }
    }
}
