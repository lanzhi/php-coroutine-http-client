<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 上午11:53
 */

namespace lanzhi\http;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class StreamBuilder
 * @package lanzhi\http
 */
class StreamBuilder
{
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @return StreamInterface
     */
    public function build():StreamInterface
    {
        $string = "{$this->request->getMethod()} {$this->getRequestUrl($this->request->getUri())} HTTP/{$this->request->getProtocolVersion()}\r\n";

        foreach ($this->request->getHeaders() as $name => $values) {
            foreach ((array)$values as $value) {
                $string .= "$name: $value\r\n";
            }
        }
        $string .= "\r\n";

        $body = $this->request->getBody();
        if($body){
            $string .= $body->getContents();
        }

        return \GuzzleHttp\Psr7\stream_for($string);
    }

    private function getRequestUrl(UriInterface $uri)
    {
        $requestUrl = $uri->getPath();

        if($uri->getQuery()){
            $requestUrl .= "?".$uri->getQuery();
        }
        if($uri->getFragment()){
            $requestUrl .= "#".$uri->getFragment();
        }

        return $requestUrl;
    }

}
