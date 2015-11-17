<?php
namespace Http\Curl;

use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * cURL raw response parser
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 *
 * @since   1.0
 */
class ResponseParser
{
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
     * Create new parser.
     *
     * @param MessageFactory $messageFactory HTTP Message factory
     * @param StreamFactory  $streamFactory  HTTP Stream factory
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory)
    {
        $this->messageFactory = $messageFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Parse cURL response
     *
     * @param string $raw  raw response
     * @param array  $info cURL response info
     *
     * @return ResponseInterface
     */
    public function parse($raw, array $info)
    {
        $response = $this->messageFactory->createResponse();

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
        $stream = $this->streamFactory->createStream($content);
        $response = $response->withBody($stream);

        return $response;
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
