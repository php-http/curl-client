<?php
namespace Http\Curl;

use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\MessageFactoryAwareTemplate;
use Http\Message\StreamFactory;
use Http\Message\StreamFactoryAwareTemplate;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-7 compatible cURL based HTTP client
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Kemist <kemist1980@gmail.com>
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author  Blake Williams <github@shabbyrobe.org>
 *
 * @since   1.0.0
 */
class CurlHttpClient implements HttpClient
{
    use MessageFactoryAwareTemplate;
    use StreamFactoryAwareTemplate;

    /**
     * cURL handle opened resource
     *
     * @var resource|null
     */
    private $handle = null;

    /**
     * Client settings
     *
     * @var array
     */
    private $settings;

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
     * @param array          $options        Client options
     *
     * @since 1.00
     */
    public function __construct(
        MessageFactory $messageFactory,
        StreamFactory $streamFactory,
        array $options = []
    ) {
        $this->setMessageFactory($messageFactory);
        $this->setStreamFactory($streamFactory);
        $this->settings = array_merge(
            [
                'curl_options' => [],
                'connection_timeout' => 3,
                'ssl_verify_peer' => true,
                'timeout' => 10
            ],
            $options
        );
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
     * @since 1.00
     */
    public function sendRequest(RequestInterface $request)
    {
        $options = $this->createCurlOptions($request);

        try {
            $this->request($options, $raw, $info);
        } catch (\RuntimeException $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }

        $response = $this->getMessageFactory()->createResponse();

        $headerSize = $info['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);
        $headers = $this->parseRawHeaders($rawHeaders);

        foreach ($headers as $header) {
            $header = trim($header);
            if ('' === $header) {
                continue;
            }

            // Status line
            if (substr(strtolower($header), 0, 5) === 'http/') {
                $parts = explode(' ', $header, 3);
                $response = $response
                    ->withStatus($parts[1])
                    ->withProtocolVersion(substr($parts[0], 5));
                continue;
            }

            // Extract header
            $parts = explode(':', $header, 2);
            $headerName = trim(urldecode($parts[0]));
            $headerValue = trim(urldecode($parts[1]));
            if ($response->hasHeader($headerName)) {
                $response = $response->withAddedHeader($headerName, $headerValue);
            } else {
                $response = $response->withHeader($headerName, $headerValue);
            }
        }

        /*
         * substr can return boolean value for empty string. But createStream does not support
         * booleans. Converting to string.
         */
        $content = (string) substr($raw, $headerSize);
        $stream = $this->getStreamFactory()->createStream($content);
        $response = $response->withBody($stream);

        return $response;
    }

    /**
     * Perform request via cURL
     *
     * @param array  $options cURL options
     * @param string $raw     raw response
     * @param array  $info    cURL response info
     *
     * @throws \RuntimeException on cURL error
     *
     * @since 1.00
     */
    protected function request($options, &$raw, &$info)
    {
        if (is_resource($this->handle)) {
            curl_reset($this->handle);
        } else {
            $this->handle = curl_init();
        }

        curl_setopt_array($this->handle, $options);
        $raw = curl_exec($this->handle);

        if (curl_errno($this->handle) > 0) {
            throw new \RuntimeException(
                sprintf(
                    'Curl error: (%d) %s',
                    curl_errno($this->handle),
                    curl_error($this->handle)
                )
            );
        }
        $info = curl_getinfo($this->handle);
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
        $options = $this->settings['curl_options'];

        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_RETURNTRANSFER] = true;

        $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        $options[CURLOPT_URL] = (string) $request->getUri();

        $options[CURLOPT_CONNECTTIMEOUT] = $this->settings['connection_timeout'];
        $options[CURLOPT_FOLLOWLOCATION] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = $this->settings['ssl_verify_peer'];
        $options[CURLOPT_TIMEOUT] = $this->settings['timeout'];

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

    /**
     * Parse raw headers from HTTP response
     *
     * @param string $rawHeaders
     *
     * @return string[]
     */
    private function parseRawHeaders($rawHeaders)
    {
        $allHeaders = explode("\r\n\r\n", $rawHeaders);
        $lastHeaders = trim(array_pop($allHeaders));
        while (count($allHeaders) > 0 && '' === $lastHeaders) {
            $lastHeaders = trim(array_pop($allHeaders));
        }
        $headers = explode("\r\n", $lastHeaders);
        return $headers;
    }
}
