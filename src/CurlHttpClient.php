<?php
namespace Http\Curl;

use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Client\Promise;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
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
class CurlHttpClient implements HttpClient, HttpAsyncClient
{
    /**
     * cURL options
     *
     * @var array
     */
    private $options;

    /**
     * cURL response parser
     *
     * @var ResponseParser
     */
    private $responseParser;

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
     * @throws \InvalidArgumentException
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

        return $this->responseParser->parse($raw, $info);
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
    private function createCurlOptions(RequestInterface $request)
    {
        $options = $this->options;

        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_FOLLOWLOCATION] = false;

        $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        $options[CURLOPT_URL] = (string) $request->getUri();

        if (in_array($request->getMethod(), ['OPTIONS', 'POST', 'PUT'], true)) {
            // cURL allows request body only for these methods.
            $body = (string) $request->getBody();
            if ('' !== $body) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
        }

        if ($request->getMethod() === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        } elseif ($request->getMethod() !== 'GET') {
            // GET is a default method. Other methods should be specified explicitly.
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
