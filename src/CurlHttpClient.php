<?php
/**
 * PSR-7 compatible cURL based HTTP client
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace Http\Curl;

use Http\Client\Exception\RequestException;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\MessageFactoryAwareTemplate;
use Http\Message\StreamFactory;
use Http\Message\StreamFactoryAwareTemplate;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * Use php cURL extension to perform HTTP requests
 *
 * @author Kemist <kemist1980@gmail.com>
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author Blake Williams <github@shabbyrobe.org>
 *
 * @api
 * @since  1.00
 */
class CurlHttpClient implements HttpClient
{
    use MessageFactoryAwareTemplate;
    use StreamFactoryAwareTemplate;

    /**
     * cURL handle configuration TODO change description
     *
     * @var array
     *
     * @since 1.00
     */
    protected $options;

    /**
     * Constructor
     *
     * Available options (see also {@link getDefaultOptions}):
     *
     * - connection_timeout : int —  connection timeout in seconds
     * - ssl_verify_peer : bool — verify peer when using SSL
     * - timeout : int —  overall timeout in seconds
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
        $this->options = array_merge(
            $this->getDefaultOptions(),
            array_intersect_key(
                $options,
                $this->getDefaultOptions()
            )
        );
    }

    /**
     * Return available options and there default values
     *
     * @return array
     *
     * @since 3.02
     */
    public function getDefaultOptions()
    {
        return [
            'curl_options' => [],
            'connection_timeout' => 3,
            'ssl_verify_peer' => true,
            'timeout' => 10
        ];
    }

    /**
     * Sends a PSR-7 request.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws UnexpectedValueException if unsupported HTTP version requested
     * @throws InvalidArgumentException
     * @throws RequestException
     *
     * @since 1.00
     */
    public function sendRequest(RequestInterface $request)
    {
        $options = $this->createCurlOptions($request);

        try {
            $this->request($options, $raw, $info);
        } catch (RuntimeException $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }

        $response = $this->getMessageFactory()->createResponse();

        $headerSize = $info['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);

        // Parse headers
        $allHeaders = explode("\r\n\r\n", $rawHeaders);
        $lastHeaders = trim(array_pop($allHeaders));
        while (count($allHeaders) > 0 && '' === $lastHeaders) {
            $lastHeaders = trim(array_pop($allHeaders));
        }
        $headerLines = explode("\r\n", $lastHeaders);
        foreach ($headerLines as $header) {
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
            $response = $this->addHeaderToResponse($response, $headerName, $headerValue);
        }

        $content = (string) substr($raw, $headerSize);
        $stream = $this->getStreamFactory()->createStream($content);
        /** @var ResponseInterface $response */
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
     * @throws RuntimeException on cURL error
     *
     * @since 3.00
     */
    protected function request($options, &$raw, &$info)
    {
        $ch = curl_init();
        try {
            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);

            if (curl_errno($ch) > 0) {
                throw new RuntimeException(
                    sprintf('Curl error: (%d) %s', curl_errno($ch), curl_error($ch))
                );
            }
            $info = curl_getinfo($ch);
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Adds a header to the response object
     *
     * @param ResponseInterface $response
     * @param string            $name
     * @param string            $value
     *
     * @return ResponseInterface
     *
     * @since 3.02
     */
    protected function addHeaderToResponse($response, $name, $value)
    {
        if ($response->hasHeader($name)) {
            $response = $response->withAddedHeader($name, $value);
        } else {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }

    /**
     * Generates cURL options
     *
     * @param RequestInterface $request
     *
     * @throws UnexpectedValueException if unsupported HTTP version requested
     *
     * @return array
     */
    private function createCurlOptions(RequestInterface $request)
    {
        $options = array_key_exists('curl_options', $this->options)
            ? $this->options['curl_options']
            : [];

        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_RETURNTRANSFER] = true;

        $options[CURLOPT_HTTP_VERSION] = $this->getProtocolVersion($request->getProtocolVersion());
        $options[CURLOPT_URL] = (string) $request->getUri();

        $options[CURLOPT_CONNECTTIMEOUT] = $this->options['connection_timeout'];
        $options[CURLOPT_FOLLOWLOCATION] = false;
        $options[CURLOPT_SSL_VERIFYPEER] = $this->options['ssl_verify_peer'];
        $options[CURLOPT_TIMEOUT] = $this->options['timeout'];

        switch ($request->getMethod()) {
            case 'HEAD':
                $options[CURLOPT_NOBODY] = true;
                break;
            case 'OPTIONS':
            case 'POST':
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
                $body = (string) $request->getBody();
                if ('' !== $body) {
                    $options[CURLOPT_POSTFIELDS] = $body;
                }
                break;
            case 'CONNECT':
            case 'DELETE':
            case 'PATCH':
            case 'TRACE':
                $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
                break;
        }

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
                $options[CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
            }
        }

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
     * @throws UnexpectedValueException if unsupported version requested
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
                throw new UnexpectedValueException('libcurl 7.33 needed for HTTP 2.0 support');
        }
        return CURL_HTTP_VERSION_NONE;
    }
}
