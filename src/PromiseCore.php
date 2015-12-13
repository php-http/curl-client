<?php
namespace Http\Curl;

use Http\Client\Exception;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared promises core.
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 */
class PromiseCore
{
    /**
     * HTTP request
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * cURL handle
     *
     * @var resource
     */
    private $handle;

    /**
     * Promise state
     *
     * @var string
     */
    private $state;

    /**
     * Exception
     *
     * @var Exception|null
     */
    private $exception = null;

    /**
     * Functions to call when a response will be available.
     *
     * @var callable[]
     */
    private $onFulfilled = [];

    /**
     * Functions to call when an error happens.
     *
     * @var callable[]
     */
    private $onRejected = [];

    /**
     * Received response
     *
     * @var ResponseInterface|null
     */
    private $response = null;

    /**
     * Create shared core.
     *
     * @param RequestInterface $request HTTP request
     * @param resource         $handle  cURL handle
     */
    public function __construct(RequestInterface $request, $handle)
    {
        assert('is_resource($handle)');
        assert('get_resource_type($handle) === "curl"');

        $this->request = $request;
        $this->handle = $handle;
        $this->state = Promise::PENDING;
    }

    /**
     * Add on fulfilled callback.
     *
     * @param callable $callback
     */
    public function addOnFulfilled(callable $callback)
    {
        if ($this->getState() === Promise::PENDING) {
            $this->onFulfilled[] = $callback;
        } elseif ($this->getState() === Promise::FULFILLED) {
            $this->response = call_user_func($callback, $this->response);
        }
    }

    /**
     * Add on rejected callback.
     *
     * @param callable $callback
     */
    public function addOnRejected(callable $callback)
    {
        if ($this->getState() === Promise::PENDING) {
            $this->onRejected[] = $callback;
        } elseif ($this->getState() === Promise::REJECTED) {
            $this->exception = call_user_func($callback, $this->exception);
        }
    }

    /**
     * Return cURL handle
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Get the state of the promise, one of PENDING, FULFILLED or REJECTED.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Return request
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Return the value of the promise (fulfilled).
     *
     * @return ResponseInterface Response Object only when the Promise is fulfilled.
     *
     * @throws \LogicException When the promise is not fulfilled.
     */
    public function getResponse()
    {
        if (null === $this->response) {
            throw new \LogicException('Promise is not fulfilled');
        }
        return $this->response;
    }

    /**
     * Get the reason why the promise was rejected.
     *
     * If the exception is an instance of Http\Client\Exception\HttpException it will contain
     * the response object with the status code and the http reason.
     *
     * @return Exception Exception Object only when the Promise is rejected.
     *
     * @throws \LogicException When the promise is not rejected.
     */
    public function getException()
    {
        if (null === $this->exception) {
            throw new \LogicException('Promise is not rejected');
        }
        return $this->exception;
    }

    /**
     * Fulfill promise.
     *
     * @param ResponseInterface $response Received response
     */
    public function fulfill(ResponseInterface $response)
    {
        $this->response = $response;
        $this->state = Promise::FULFILLED;
        $this->response = $this->call($this->onFulfilled, $this->response);
    }

    /**
     * Reject promise.
     *
     * @param Exception $exception Reject reason.
     */
    public function reject(Exception $exception)
    {
        $this->exception = $exception;
        $this->state = Promise::REJECTED;

        try {
            $this->call($this->onRejected, $this->exception);
        } catch (Exception $exception) {
            $this->exception = $exception;
        }
    }

    /**
     * Call functions.
     *
     * @param callable[] $callbacks on fulfill or on reject callback queue
     * @param mixed      $argument  response or exception
     *
     * @return mixed response or exception
     */
    private function call(array &$callbacks, $argument)
    {
        while (count($callbacks) > 0) {
            $callback = array_shift($callbacks);
            $argument = call_user_func($callback, $argument);
        }
        return $argument;
    }
}
