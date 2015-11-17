<?php
namespace Http\Curl\Tests;

use Http\Client\Promise;
use Http\Curl\CurlPromise;
use Http\Curl\MultiRunner;

/**
 * Tests for Http\Curl\CurlPromise
 *
 * @covers Http\Curl\CurlPromise
 */
class CurlPromiseTest extends BaseUnitTestCase
{
    /**
     * Test that promise call core methods
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

        $core->expects(static::once())->method('getResponse')->willReturn('RESPONSE');
        static::assertEquals('RESPONSE', $promise->getResponse());

        $core->expects(static::once())->method('getException')->willReturn('EXCEPTION');
        static::assertEquals('EXCEPTION', $promise->getException());

        $runner->expects(static::once())->method('wait')->with($core);
        $promise->wait();
    }
}
