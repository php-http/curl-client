<?php

namespace Http\Client\Curl;

use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
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
 * @author  Dmitry Arhitector <dmitry.arhitector@yandex.ru>
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
    protected $options;

    /**
     * cURL response parser
     *
     * @var ResponseParser
     */
    protected $responseParser;

    /**
     * cURL synchronous requests handle
     *
     * @var resource|null
     */
    protected $handle = null;

    /**
     * Simultaneous requests runner
     *
     * @var MultiRunner|null
     */
    protected $multiRunner = null;


    /**
     * Create new client
     *
     * Available options:
     *
     * - connection_timeout : int —  connection timeout in seconds;
     * - curl_options: array — custom cURL options;
     * - ssl_verify_peer : bool — verify peer when using SSL;
     * - timeout : int —  overall timeout in seconds.
     *
     * @param MessageFactory $messageFactory HTTP Message factory
     * @param StreamFactory  $streamFactory  HTTP Stream factory
     * @param array          $options        cURL options (see http://php.net/curl_setopt)
     *
     * @since 1.0
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory, array $options = [])
    {
        $this->responseParser = new ResponseParser($messageFactory, $streamFactory);
        $this->options = $options;
    }

    /**
     * Sends a PSR-7 request.
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
     *
     * @return ResponseInterface
     *
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     * @throws RequestException
     *
     * @since 1.0
     */
    public function sendRequest(RequestInterface $request, array $options = [])
    {
        if (is_resource($this->handle)) {
            curl_reset($this->handle);
        } else {
            $this->handle = curl_init();
        }

        $options = $this->createCurlOptions($request, $options);

        curl_setopt_array($this->handle, $options);

        if ( ! curl_exec($this->handle)) {
            throw new RequestException(curl_error($this->handle), $request);
        }

        try {
            $response = $this->responseParser->parse($options[CURLOPT_FILE], curl_getinfo($this->handle));
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }
        return $response;
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
     *
     * @return Promise
     *
     * @throws Exception
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @since 1.0
     */
    public function sendAsyncRequest(RequestInterface $request, array $options = [])
    {
        if ( ! $this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner($this->responseParser);
        }

        $handle = curl_init();
        $options = $this->createCurlOptions($request, $options);

        curl_setopt_array($handle, $options);

        $core = new PromiseCore($request, $handle);
        $promise = new CurlPromise($core, $this->multiRunner);

        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Get parser
     *
     * @return ResponseParser
     */
    public function getResponseParser()
    {
        return $this->responseParser;
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
     * Generates cURL options
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
     *
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @return array
     */
    protected function createCurlOptions(RequestInterface $request, array $options = [])
	{
        // Invalid overwrite Curl options.
        $options = array_diff_key($options + $this->options, array_flip([CURLOPT_INFILE, CURLOPT_INFILESIZE]));
        $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        $options[CURLOPT_HEADERFUNCTION] = [$this->getResponseParser(), 'headerHandler'];
        $options[CURLOPT_URL] = (string) $request->getUri();
        $options[CURLOPT_RETURNTRANSFER] = false;
        $options[CURLOPT_FILE] = $this->getResponseParser()->getTemporaryStream();
        $options[CURLOPT_HEADER] = false;

        // These methods do not transfer body.
        // You can specify any method you'd like, including a custom method that might not be part of RFC 7231 (like "MOVE").
		if (in_array($request->getMethod(), ['GET', 'HEAD', 'TRACE', 'CONNECT'])) {
			if ($request->getMethod() == 'HEAD') {
				$options[CURLOPT_NOBODY] = true;

                unset($options[CURLOPT_READFUNCTION], $options[CURLOPT_WRITEFUNCTION]);
			}
		} else {
			$body = clone $request->getBody();
			$size = $body->getSize();

			if ($size === null || $size > 1048576) {
                $body->rewind();
                $options[CURLOPT_UPLOAD] = true;

                // Avoid full loading large or unknown size body into memory. Not replace CURLOPT_READFUNCTION.
                if (isset($options[CURLOPT_READFUNCTION]) && is_callable($options[CURLOPT_READFUNCTION])) {
                    $body = $body->detach();
                    $options[CURLOPT_READFUNCTION] = function ($curlHandler, $handler, $length) use ($body, $options) {
                        return call_user_func($options[CURLOPT_READFUNCTION], $curlHandler, $body, $length);
                    };
                } else {
                    $options[CURLOPT_READFUNCTION] = function ($curl, $handler, $length) use ($body) {
                        return $body->read($length);
                    };
                }
			} else {
                // Send the body as a string if the size is less than 1MB.
				$options[CURLOPT_POSTFIELDS] = (string) $request->getBody();
			}
		}

		if ($request->getMethod() != 'GET') {
			$options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
		}

		$options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

		if ($request->getUri()->getUserInfo()) {
			$options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
		}

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
    protected function getProtocolVersion($requestVersion)
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
    protected function createHeaders(RequestInterface $request, array $options)
    {
        $curlHeaders = [];
        $headers = array_keys($request->getHeaders());

        if ( ! $request->hasHeader('Expect')) {
            $curlHeaders[] = 'Expect:';
        }

        if ( ! $request->hasHeader('Accept')) {
            $curlHeaders[] = 'Accept: */*';
        }

        foreach ($headers as $name) {
            if (strtolower($name) === 'content-length') {
                $values = [0];

                if (array_key_exists(CURLOPT_POSTFIELDS, $options)) {
                    $values = [strlen($options[CURLOPT_POSTFIELDS])];
                }
            } else {
                $values = $request->getHeader($name);
            }

            foreach ($values as $value) {
                $curlHeaders[] = $name. ': '.$value;
            }
        }

        return $curlHeaders;
    }

}