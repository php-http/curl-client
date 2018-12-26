<?php

declare(strict_types=1);

namespace Http\Client\Curl;

use Http\Client\Exception;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * PSR-7 compatible cURL based HTTP client.
 *
 * @license http://opensource.org/licenses/MIT MIT
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author  Blake Williams <github@shabbyrobe.org>
 *
 * @api
 *
 * @since   1.0
 */
class Client implements HttpClient, HttpAsyncClient
{
    /**
     * cURL options.
     *
     * @var array
     */
    private $options;

    /**
     * PSR-17 response factory.
     *
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * PSR-17 stream factory.
     *
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * cURL synchronous requests handle.
     *
     * @var resource|null
     */
    private $handle;

    /**
     * Simultaneous requests runner.
     *
     * @var MultiRunner|null
     */
    private $multiRunner;

    /**
     * Construct client.
     *
     * @param ResponseFactoryInterface|null $responseFactory PSR-17 HTTP response factory.
     * @param StreamFactoryInterface|null   $streamFactory   PSR-17 HTTP stream factory.
     * @param array                         $options         cURL options {@link http://php.net/curl_setopt}
     *
     * @throws \Http\Discovery\Exception\NotFoundException If factory discovery failed
     *
     * @since x.x $messageFactory changed to PSR-17 ResponseFactoryInterface $responseFactory.
     * @since x.x $streamFactory type changed to PSR-17 StreamFactoryInterface.
     * @since 1.0
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory = null,
        StreamFactoryInterface $streamFactory = null,
        array $options = []
    ) {
        $this->responseFactory = $responseFactory; // FIXME ?: MessageFactoryDiscovery::find();
        $this->streamFactory = $streamFactory; // FIXME ?: StreamFactoryDiscovery::find();
        $resolver = new OptionsResolver();
        $resolver->setDefaults(
            [
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
            ]
        );
        $resolver->setAllowedValues(CURLOPT_HEADER, [false]); // our parsing will fail if this is set to true
        $resolver->setAllowedValues(CURLOPT_RETURNTRANSFER, [false]); // our parsing will fail if this is set to true

        // We do not know what everything curl supports and might support in the future.
        // Make sure that we accept everything that is in the options.
        $resolver->setDefined(array_keys($options));

        $this->options = $resolver->resolve($options);
    }

    /**
     * Release resources if still active.
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
    }

    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \Http\Client\Exception\NetworkException In case of network problems
     * @throws \Http\Client\Exception\RequestException On invalid request
     * @throws \InvalidArgumentException               For invalid header names or values
     * @throws \RuntimeException                       If creating the body stream fails
     *
     * @since 1.6 \UnexpectedValueException replaced with RequestException
     * @since 1.6 Throw NetworkException on network errors
     * @since 1.0
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $responseBuilder = $this->createResponseBuilder();
        $requestOptions = $this->prepareRequestOptions($request, $responseBuilder);

        if (is_resource($this->handle)) {
            curl_reset($this->handle);
        } else {
            $this->handle = curl_init();
        }

        curl_setopt_array($this->handle, $requestOptions);
        curl_exec($this->handle);

        $errno = curl_errno($this->handle);
        switch ($errno) {
            case CURLE_OK:
                // All OK, no actions needed.
                break;
            case CURLE_COULDNT_RESOLVE_PROXY:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_CONNECT:
            case CURLE_OPERATION_TIMEOUTED:
            case CURLE_SSL_CONNECT_ERROR:
                throw new Exception\NetworkException(curl_error($this->handle), $request);
            default:
                throw new Exception\RequestException(curl_error($this->handle), $request);
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
     * @throws \Http\Client\Exception\RequestException On invalid request
     * @throws \InvalidArgumentException               For invalid header names or values
     * @throws \RuntimeException                       If creating the body stream fails
     *
     * @since 1.6 \UnexpectedValueException replaced with RequestException
     * @since 1.0
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        if (!$this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner();
        }

        $handle = curl_init();
        $responseBuilder = $this->createResponseBuilder();
        $requestOptions = $this->prepareRequestOptions($request, $responseBuilder);
        curl_setopt_array($handle, $requestOptions);

        $core = new PromiseCore($request, $handle, $responseBuilder);
        $promise = new CurlPromise($core, $this->multiRunner);
        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Update cURL options for this request and hook in the response builder.
     *
     * @param RequestInterface $request
     * @param ResponseBuilder  $responseBuilder
     *
     * @throws \Http\Client\Exception\RequestException On invalid request
     * @throws \InvalidArgumentException               For invalid header names or values
     * @throws \RuntimeException                       If can not read body
     *
     * @return array
     */
    private function prepareRequestOptions(RequestInterface $request, ResponseBuilder $responseBuilder)
    {
        $options = $this->options;

        try {
            $options[CURLOPT_HTTP_VERSION]
                = $this->getProtocolVersion($request->getProtocolVersion());
        } catch (\UnexpectedValueException $e) {
            throw new Exception\RequestException($e->getMessage(), $request);
        }
        $options[CURLOPT_URL] = (string) $request->getUri();

        $options = $this->addRequestBodyOptions($request, $options);

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
     * Return cURL constant for specified HTTP version.
     *
     * @param string $requestVersion
     *
     * @throws \UnexpectedValueException If unsupported version requested
     *
     * @return int
     */
    private function getProtocolVersion(string $requestVersion): int
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
     * Add request body related cURL options.
     *
     * @param RequestInterface $request
     * @param array            $options
     *
     * @return array
     */
    private function addRequestBodyOptions(RequestInterface $request, array $options): array
    {
        /*
         * Some HTTP methods cannot have payload:
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
                if ($body->isSeekable()) {
                    $body->rewind();
                }

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

        return $options;
    }

    /**
     * Create headers array for CURLOPT_HTTPHEADER.
     *
     * @param RequestInterface $request
     * @param array            $options cURL options
     *
     * @return string[]
     */
    private function createHeaders(RequestInterface $request, array $options): array
    {
        $curlHeaders = [];
        $headers = $request->getHeaders();
        foreach ($headers as $name => $values) {
            $header = strtolower($name);
            if ('expect' === $header) {
                // curl-client does not support "Expect-Continue", so dropping "expect" headers
                continue;
            }
            if ('content-length' === $header) {
                if (array_key_exists(CURLOPT_POSTFIELDS, $options)) {
                    // Small body content length can be calculated here.
                    $values = [strlen($options[CURLOPT_POSTFIELDS])];
                } elseif (!array_key_exists(CURLOPT_READFUNCTION, $options)) {
                    // Else if there is no body, forcing "Content-length" to 0
                    $values = [0];
                }
            }
            foreach ($values as $value) {
                $curlHeaders[] = $name.': '.$value;
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
     * Create new ResponseBuilder instance.
     *
     * @return ResponseBuilder
     */
    private function createResponseBuilder(): ResponseBuilder
    {
        $body = $this->streamFactory->createStreamFromFile('php://temp', 'w+b');

        $response = $this->responseFactory
            ->createResponse(200)
            ->withBody($body);

        return new ResponseBuilder($response);
    }
}
