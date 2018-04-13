<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 上午11:16
 */

namespace lanzhi\http;


use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ResponseBuilder
 * @package lanzhi\http
 */
class ResponseBuilder
{
    /**
     * @var string
     */
    private $startLine;
    /**
     * @var string
     */
    private $header;
    /**
     * @var string
     */
    private $body;

    /**
     * ResponseBuilder constructor.
     * @param string $startLine
     * @param string $header
     * @param string|null $body
     */
    public function __construct(string $startLine, string $header, string $body=null)
    {
        $this->startLine = $startLine;
        $this->header    = $header;
        $this->body      = $body;
    }

    public function build():ResponseInterface
    {
        list($status, $version, $reason) = $this->parseStartLine($this->startLine);

        $headers = $this->parseHeader($this->header);
        return new Response($status, $headers, $this->body, $version, $reason);
    }

    /**
     * @param string $startLine
     * @return array [status, version, reason]
     * @throws \Exception
     */
    private function parseStartLine(string $startLine)
    {
        $pattern = '/HTTP\/(.*) (.*) (.*)/';
        if(!preg_match($pattern, $startLine, $matches) || count($matches)!=4){
            throw new \Exception("parse http message first line error; line:{$startLine}");
        }

        return [$matches[2], $matches[1], $matches[3]];
    }

    /**
     * @param string $header
     * @return array
     */
    private function parseHeader(string $header)
    {
        $headers = [];
        $header = explode("\r\n", $header);
        foreach ($header as $index=>$line){
            if(strpos($line, ':')===false){
                continue;
            }

            list($key, $value) = explode(':', $line);
            $value = trim($value);

            if(empty($headers[$key])){
                $headers[$key] = [$value];
            }else{
                $headers[$key][] = $value;
            }
        }

        return $headers;
    }

}