<?php
namespace Http\Client\Curl;

use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-7 compatible cURL based HTTP client
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author  Blake Williams <github@shabbyrobe.org>
 *
 * @api
 * @since   1.0
 */
class Client implements HttpClient, HttpAsyncClient
{
    /**
     * cURL options
     *
     * @var array
     */
    private $options;

    /**
     * PSR-7 message factory
     *
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * PSR-7 stream factory
     *
     * @var StreamFactory
     */
    private $streamFactory;

    /**
     * cURL synchronous requests handle
     *
     * @var resource|null
     */
    private $handle = null;

    /**
     * Simultaneous requests runner
     *
     * @var MultiRunner|null
     */
    private $multiRunner = null;

    /**
     * Create new client
     *
     * @param MessageFactory|null $messageFactory HTTP Message factory
     * @param StreamFactory|null  $streamFactory  HTTP Stream factory
     * @param array               $options        cURL options (see http://php.net/curl_setopt)
     *
     * @throws \Http\Discovery\Exception\NotFoundException If factory discovery failed.
     *
     * @since 1.0
     */
    public function __construct(
        MessageFactory $messageFactory = null,
        StreamFactory $streamFactory = null,
        array $options = []
    ) {
        $this->messageFactory = $messageFactory ?: MessageFactoryDiscovery::find();
        $this->streamFactory = $streamFactory ?: StreamFactoryDiscovery::find();
        $this->options = $options;
    }

    /**
     * Release resources if still active
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
    }

    /**
     * Sends a PSR-7 request.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException For invalid header names or values.
     * @throws \RuntimeException         If creating the body stream fails.
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     * @throws RequestException
     *
     * @since 1.0
     */
    public function sendRequest(RequestInterface $request)
    {
        $responseBuilder = $this->createResponseBuilder();
        $options = $this->createCurlOptions($request, $responseBuilder);

        if (is_resource($this->handle)) {
            curl_reset($this->handle);
        } else {
            $this->handle = curl_init();
        }

        curl_setopt_array($this->handle, $options);
        curl_exec($this->handle);

        if (curl_errno($this->handle) > 0) {
            throw new RequestException(curl_error($this->handle), $request);
        }

        $response = $responseBuilder->getResponse();
        $response->getBody()->seek(0);

        return $response;
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * @param RequestInterface $request
     *
     * @return Promise
     *
     * @throws \InvalidArgumentException For invalid header names or values.
     * @throws \RuntimeException         If creating the body stream fails.
     * @throws \UnexpectedValueException If unsupported HTTP version requested
     * @throws Exception
     *
     * @since 1.0
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        if (!$this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner();
        }

        $handle = curl_init();
        $responseBuilder = $this->createResponseBuilder();
        $options = $this->createCurlOptions($request, $responseBuilder);
        curl_setopt_array($handle, $options);

        $core = new PromiseCore($request, $handle, $responseBuilder);
        $promise = new CurlPromise($core, $this->multiRunner);
        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Generates cURL options
     *
     * @param RequestInterface $request
     * @param ResponseBuilder  $responseBuilder
     *
     * @throws \InvalidArgumentException For invalid header names or values.
     * @throws \RuntimeException if can not read body
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @return array
     */
    private function createCurlOptions(RequestInterface $request, ResponseBuilder $responseBuilder)
    {
        $options = $this->options;

        $options[CURLOPT_HEADER] = false;
        $options[CURLOPT_RETURNTRANSFER] = false;
        $options[CURLOPT_FOLLOWLOCATION] = false;

        $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        $options[CURLOPT_URL] = (string) $request->getUri();

        /*
         * Add body to request. Some HTTP methods can not have payload:
         *
         * - GET — cURL will automatically change method to PUT or POST if we set CURLOPT_UPLOAD or
         *   CURLOPT_POSTFIELDS.
         * - HEAD — cURL treats HEAD as GET request with a same restrictions.
         * - TRACE — According to RFC7231: a client MUST NOT send a message body in a TRACE request.
         */
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'TRACE'], true)) {
            $body = $request->getBody();
            $bodySize = $body->getSize();
            if ($bodySize !== 0) {
                // Message has non empty body.
                if (null === $bodySize || $bodySize > 1024 * 1024) {
                    // Avoid full loading large or unknown size body into memory
                    $options[CURLOPT_UPLOAD] = true;
                    if (null !== $bodySize) {
                        $options[CURLOPT_INFILESIZE] = $bodySize;
                    }
                    $options[CURLOPT_READFUNCTION] = function ($ch, $fd, $length) use ($body) {
                        return $body->read($length);
                    };
                } else {
                    // Small body can be loaded into memory
                    $options[CURLOPT_POSTFIELDS] = (string) $body;
                }
            }
        }

        if ($request->getMethod() === 'HEAD') {
            // This will set HTTP method to "HEAD".
            $options[CURLOPT_NOBODY] = true;
        } elseif ($request->getMethod() !== 'GET') {
            // GET is a default method. Other methods should be specified explicitly.
            $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        }

        $options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

        if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

        $options[CURLOPT_HEADERFUNCTION] = function ($ch, $data) use ($responseBuilder) {
            $str = trim($data);
            if ('' !== $str) {
                if (strpos(strtolower($str), 'http/') === 0) {
                    $responseBuilder->setStatus($str)->getResponse();
                } else {
                    $responseBuilder->addHeader($str);
                }
            }

            return strlen($data);
        };

        $options[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use ($responseBuilder) {
            return $responseBuilder->getResponse()->getBody()->write($data);
        };

        return $options;
    }

    /**
     * Return cURL constant for specified HTTP version
     *
     * @param string $requestVersion
     *
     * @throws \UnexpectedValueException if unsupported version requested
     *
     * @return int
     */
    private function getProtocolVersion($requestVersion)
    {
        switch ($requestVersion) {
            case '1.0':
                return CURL_HTTP_VERSION_1_0;
            case '1.1':
                return CURL_HTTP_VERSION_1_1;
            case '2.0':
                if (defined('CURL_HTTP_VERSION_2_0')) {
                    return CURL_HTTP_VERSION_2_0;
                }
                throw new \UnexpectedValueException('libcurl 7.33 needed for HTTP 2.0 support');
        }

        return CURL_HTTP_VERSION_NONE;
    }

    /**
     * Create headers array for CURLOPT_HTTPHEADER
     *
     * @param RequestInterface $request
     * @param array            $options cURL options
     *
     * @return string[]
     */
    private function createHeaders(RequestInterface $request, array $options)
    {
        $curlHeaders = [];
        $headers = array_keys($request->getHeaders());
        foreach ($headers as $name) {
            $header = strtolower($name);
            if ('expect' === $header) {
                // curl-client does not support "Expect-Continue", so dropping "expect" headers
                continue;
            }
            if ('content-length' === $header) {
                $values = [0];
                if (array_key_exists(CURLOPT_POSTFIELDS, $options)) {
                    $values = [strlen($options[CURLOPT_POSTFIELDS])];
                }
            } else {
                $values = $request->getHeader($name);
            }
            foreach ($values as $value) {
                $curlHeaders[] = $name . ': ' . $value;
            }
        }
        /*
         * curl-client does not support "Expect-Continue", but cURL adds "Expect" header by default.
         * We can not suppress it, but we can set it to empty.
         */
        $curlHeaders[] = 'Expect:';

        return $curlHeaders;
    }

    /**
     * Create new ResponseBuilder instance
     *
     * @return ResponseBuilder
     *
     * @throws \RuntimeException If creating the stream from $body fails.
     */
    private function createResponseBuilder()
    {
        try {
            $body = $this->streamFactory->createStream(fopen('php://temp', 'w+'));
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('Can not create "php://temp" stream.');
        }
        $response = $this->messageFactory->createResponse(200, null, [], $body);

        return new ResponseBuilder($response);
    }
}
