<?php
namespace Http\Client\Curl\Tests\Tools;

use Http\Client\Curl\Tools\HeadersParser;
use Http\Discovery\MessageFactoryDiscovery;

/**
 * @covers Http\Curl\Tools\HeadersParser
 */
class HeadersParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test valid headers parsing
     */
    public function testValidHeaders()
    {
        $headers = file_get_contents(__DIR__ . '/data/headers_valid.http');
        $response = MessageFactoryDiscovery::find()->createResponse();
        $parser = new HeadersParser();
        $response = $parser->parseString($headers, $response);
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('OK', $response->getReasonPhrase());
        static::assertEquals('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        static::assertEquals(['foo=1234', 'bar=4321'], $response->getHeader('Set-Cookie'));
    }

    /**
     * Test parsing headers with invalid status line
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "HTTP/1.1" is not a valid HTTP status line
     */
    public function testInvalidStatusLine()
    {
        $headers = file_get_contents(__DIR__ . '/data/headers_invalid_status.http');
        $response = MessageFactoryDiscovery::find()->createResponse();
        $parser = new HeadersParser();
        $parser->parseString($headers, $response);
    }

    /**
     * Test parsing headers with invalid header line
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage "Content-Type text/html" is not a valid HTTP header line
     */
    public function testInvalidHeaderLine()
    {
        $headers = file_get_contents(__DIR__ . '/data/headers_invalid_header.http');
        $response = MessageFactoryDiscovery::find()->createResponse();
        $parser = new HeadersParser();
        $parser->parseString($headers, $response);
    }


    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage HeadersParser::parseString expects parameter 1 to be a string, array given
     */
    public function testInvalidArgument()
    {
        $response = MessageFactoryDiscovery::find()->createResponse();
        $parser = new HeadersParser();
        $parser->parseString([], $response);
    }
}
