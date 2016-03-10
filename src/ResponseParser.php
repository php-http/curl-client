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
 * @author  Dmitry Arhitector   <dmitry.arhitector@yandex.ru>
*/
class ResponseParser
{

    /**
     * Raw response headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * PSR-7 message factory
     *
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * PSR-7 stream factory
     *
     * @var StreamFactory
     */
    protected $streamFactory;

    /**
     * Temporary resource
     *
     * @var resource
     */
    protected $temporaryStream;

    /**
     * Receive redirect
     *
     * @var bool
     */
    protected $followLocation = false;


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
     * Get factory
     *
     * @return MessageFactory
     */
    public function getMessageFactory()
    {
        return $this->messageFactory;
    }

    /**
     * Get factory
     *
     * @return StreamFactory
     */
    public function getStreamFactory()
    {
        return $this->streamFactory;
    }

    /**
     * Temporary body (fix out of memory)
     *
     * @return resource
     */
    public function getTemporaryStream()
    {
        if ( ! is_resource($this->temporaryStream))
        {
            $this->temporaryStream = fopen('php://temp', 'w+');
        }

        return $this->temporaryStream;
    }

    /**
     * Parse cURL response
     *
     * @param resource $raw  raw response
     * @param array    $info cURL response info
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function parse($raw = null, array $info)
    {
        if (empty($raw)) // fix promise out of memory
        {
            $raw = $this->getTemporaryStream();
        }

        $parser = new HeadersParser();

        $response = $parser->parseArray($this->headers, $this->messageFactory->createResponse());
        $response = $response->withBody($this->streamFactory->createStream($raw));

        $this->temporaryStream = null;

        return $response;
    }

    /**
     * Save the response headers
     * 
     * @param   resource    $handler    curl handler
     * @param   string      $header     raw header
     * 
     * @return integer
     */
    public function headerHandler($handler, $header)
    {
        $this->headers[] = $header;

        if ($this->followLocation) {
            $this->followLocation = false;
            $this->headers = [$header];
        } else if ( ! trim($header)) {
            $this->followLocation = true;
            //$this->parse(null, []);
        }

        return strlen($header);
    }

}
