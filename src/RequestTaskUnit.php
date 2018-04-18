<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/3
 * Time: 下午2:02
 */

namespace lanzhi\http;


use Generator;
use lanzhi\http\exceptions\HttpException;
use lanzhi\http\exceptions\RedirectTooManyTimesException;
use lanzhi\socket\ConnectionInterface;
use lanzhi\socket\connection;
use lanzhi\socket\Connector;
use lanzhi\socket\ConnectorInterface;
use Psr\Http\Message\ResponseInterface;
use lanzhi\coroutine\AbstractTaskUnit;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class TaskUnit
 * @package lanzhi\http
 *
 * @method ResponseInterface getReturn()
 */
class RequestTaskUnit extends AbstractTaskUnit
{
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ConnectorInterface
     */
    private $connector;
    /**
     * @var false | int
     */
    private $allowRedirects;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * RequestTaskUnit constructor.
     * @param RequestInterface $request
     * @param ConnectorInterface $connector
     * @param int $allowRedirects
     * @param LoggerInterface|null $logger
     */
    public function __construct(RequestInterface $request, ConnectorInterface $connector, int $allowRedirects, LoggerInterface $logger=null)
    {
        $this->request        = $request;
        $this->connector      = $connector;
        $this->allowRedirects = $allowRedirects;
        $this->logger         = $logger ?? new NullLogger();

        parent::__construct($logger);
    }

    /**
     * 在此处支持重定向
     * @return Generator
     */
    protected function generate(): Generator
    {
        $request = $this->request;
        $remains = $this->allowRedirects;

        request:
        list($scheme, $host, $port) = Connector::parseUri($request->getUri());
        $connection = $this->connector->get($scheme, $host, $port);

        $data = (new StreamBuilder($request))->build()->getContents();
        yield from $connection->write($data, true);

        $handle = new ReadHandler($this->logger);
        yield from $connection->read($handle);

        //连接使用过之后，归还连接器
        $this->connector->back($connection);

        $builder = new ResponseBuilder(
            $handle->getStartLine(),
            $handle->getHeaders(),
            $handle->getBody()
        );
        $response = $builder->build();
        $statusCode = $response->getStatusCode();
        if($this->allowRedirects && ($statusCode==301 || $statusCode==302)){
            if($remains--){
                $request = $this->buildNewRequest($request, $response);
                goto request;
            }else{
                throw new RedirectTooManyTimesException($this->request->getUri()->__toString(), $this->allowRedirects);
            }
        }else{
            return $response;
        }
    }

    /**
     * 使用响应中 Location 首部替换当前请求的 URI
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    private function buildNewRequest(RequestInterface $request, ResponseInterface $response)
    {
        /**
         * @var UriInterface $uri
         */
        $location = $response->getHeader('Location');
        if(empty($location)){
            throw new HttpException("redirect response don't has a Location header;");//todo
        }
        $parts = parse_url($location);
        $uri = $request->getUri();
        $uri = isset($parts['scheme'])   ? $uri->withScheme($parts['scheme']) : $uri;
        $uri = isset($parts['host'])     ? $uri->withHost($parts['host'])     : $uri;
        $uri = isset($parts['port'])     ? $uri->withPort($parts['port'])     : $uri;
        $uri = isset($parts['user'])     ? $uri->withUserInfo($parts['user'], $parts['pass'] ?? null) : $uri;
        $uri = isset($parts['path'])     ? $uri->withPath($parts['path'])         : $uri;
        $uri = isset($parts['query'])    ? $uri->withQuery($parts['query'])       : $uri;
        $uri = isset($parts['fragment']) ? $uri->withFragment($parts['fragment']) : $uri;

        return $request->withUri($uri);
    }

}