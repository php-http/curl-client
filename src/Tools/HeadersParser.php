<?php
namespace Http\Client\Curl\Tools;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response headers parser
 */
class HeadersParser
{
    /**
     * Parse headers and write them to response object.
     *
     * @param string[]          $headers  Response headers as array of header lines.
     * @param ResponseInterface $response Response to write headers to.
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException For invalid status code arguments.
     * @throws \RuntimeException
     */
    public function parseArray(array $headers, ResponseInterface $response)
    {
        $statusLine = trim(array_shift($headers));
        $parts = explode(' ', $statusLine, 3);
        if (count($parts) < 2 || substr(strtolower($parts[0]), 0, 5) !== 'http/') {
            throw new \RuntimeException(
                sprintf('"%s" is not a valid HTTP status line', $statusLine)
            );
        }

        $reasonPhrase = count($parts) > 2 ? $parts[2] : '';
        /** @var ResponseInterface $response */
        $response = $response
            ->withStatus((int) $parts[1], $reasonPhrase)
            ->withProtocolVersion(substr($parts[0], 5));

        foreach ($headers as $headerLine) {
            $headerLine = trim($headerLine);
            if ('' === $headerLine) {
                continue;
            }

            $parts = explode(':', $headerLine, 2);
            if (count($parts) !== 2) {
                throw new \RuntimeException(
                    sprintf('"%s" is not a valid HTTP header line', $headerLine)
                );
            }
            $name = trim(urldecode($parts[0]));
            $value = trim(urldecode($parts[1]));
            if ($response->hasHeader($name)) {
                $response = $response->withAddedHeader($name, $value);
            } else {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Parse headers and write them to response object.
     *
     * @param string            $headers  Response headers as single string.
     * @param ResponseInterface $response Response to write headers to.
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException if $headers is not a string on object with __toString()
     * @throws \RuntimeException
     */
    public function parseString($headers, ResponseInterface $response)
    {
        if (!(is_string($headers)
            || (is_object($headers) && method_exists($headers, '__toString')))
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s expects parameter 1 to be a string, %s given',
                    __METHOD__,
                    is_object($headers) ? get_class($headers) : gettype($headers)
                )
            );
        }
        return $this->parseArray(explode("\r\n", $headers), $response);
    }
}
