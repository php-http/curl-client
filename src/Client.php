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
 * 
 * @TODO
 * не работают фильтры потоков Http\Message\Encoding т.к. используют копию php://memory,
 * что очень плохо потому что это приводит к неконтролируемому расходу памяти и это противоречит PSR-7.
 * Лучшее решение это отказаться от clue и использовать stream_copy_to_stream($stream, fopen('php://temp', 'wb'))
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
    public function __construct(
        MessageFactory $messageFactory,
        StreamFactory $streamFactory,
        array $options = []
    ) {
        $this->responseParser = new ResponseParser($messageFactory, $streamFactory);
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
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     * @throws RequestException
     *
     * @since 1.0
     */
    public function sendRequest(RequestInterface $request)
    {
        $options = $this->createCurlOptions($request);

        if (is_resource($this->handle)) {
            curl_reset($this->handle);
        } else {
            $this->handle = curl_init();
        }

        curl_setopt_array($this->handle, $options);
        $raw = curl_exec($this->handle);

        if (curl_errno($this->handle) > 0) {
            throw new RequestException(curl_error($this->handle), $request);
        }

        $info = curl_getinfo($this->handle);

        try {
            $response = $this->responseParser->parse($raw, $info);
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }
        return $response;
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * @param RequestInterface $request
     *
     * @return Promise
     *
     * @throws Exception
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @since 1.0
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        if (!$this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner($this->responseParser);
        }

        $handle = curl_init();
        $options = $this->createCurlOptions($request);
        curl_setopt_array($handle, $options);

        $core = new PromiseCore($request, $handle);
        $promise = new CurlPromise($core, $this->multiRunner);
        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Generates cURL options
     *
     * @param RequestInterface $request
     *
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @return array
     */
    protected function createCurlOptions(RequestInterface $request)
	{
		// Invalid overwrite Curl options.
		$options = array_diff_key($this->options, array_flip([
			CURLOPT_HTTPGET,
			CURLOPT_POST,
			CURLOPT_UPLOAD,
			CURLOPT_CUSTOMREQUEST,
			CURLOPT_HTTPHEADER,
			CURLOPT_INFILE,
			CURLOPT_INFILESIZE
		]));

		$options[CURLOPT_HEADER] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_FOLLOWLOCATION] = false;
		$options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
		$options[CURLOPT_URL] = (string) $request->getUri();

		// These methods do not transfer body.
		// You can specify any method you'd like, including a custom method that might not be part of RFC 7231 (like "MOVE").
		if (in_array($request->getMethod(), ['GET', 'HEAD', 'TRACE', 'CONNECT'])) {
			// Make cancellation CURLOPT_WRITEFUNCTION, CURLOPT_READFUNCTION ? I have not tested.
			if ($request->getMethod() == 'HEAD') {
				$options[CURLOPT_NOBODY] = true;
			}
		} else { // Allow custom methods with body transfer (PUT, PROPFIND and other.)
			$body = clone $request->getBody();
			$size = $body->getSize();

			// Send the body if the size is more than 1MB OR if the.
			// The file to PUT must be set with CURLOPT_INFILE and CURLOPT_INFILESIZE.
			if ($size === null || $size > 1024 * 1024) {
				$options[CURLOPT_UPLOAD] = true;

				// Note that using this option will not stop libcurl from sending more data,
				// as exactly what is sent depends on CURLOPT_READFUNCTION.
				if ($size !== null) {
					$options[CURLOPT_INFILESIZE] = $size;
				}

				$body->rewind();

				// Avoid full loading large or unknown size body into memory. Not replace CURLOPT_READFUNCTION.
				$options[CURLOPT_INFILE] = $body->detach();
			} else {
				// Send the body as a string if the size is less than 1MB.
				$options[CURLOPT_POSTFIELDS] = (string) $request->getBody();
			}
		}

		// GET is a default method. Other methods should be specified explicitly.
		if ($request->getMethod() != 'GET') {
			$options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
		}

		// For PUT and POST need Content-Length see RFC 7230 section 3.3.2
		$options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

		if ($request->getUri()->getUserInfo()) {
			$options[CURLOPT_USERPWD] = $request->getUri()
				->getUserInfo();
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
                $curlHeaders[] = $name . ': ' . $value;
            }
        }
        return $curlHeaders;
    }
}
