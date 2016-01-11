<?php
namespace Http\Client\Curl;

use Http\Client\Curl\Tools\HeadersParser;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * cURL raw response parser
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
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
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function parse($raw, array $info)
    {
        $response = $this->messageFactory->createResponse();

        $headerSize = $info['header_size'];
        $rawHeaders = substr($raw, 0, $headerSize);

        $parser = new HeadersParser();
        $response = $parser->parseString($rawHeaders, $response);

        /*
         * substr can return boolean value for empty string. But createStream does not support
         * booleans. Converting to string.
         */
        $content = (string) substr($raw, $headerSize);
        $stream = $this->streamFactory->createStream($content);
        $response = $response->withBody($stream);

        return $response;
    }
}
