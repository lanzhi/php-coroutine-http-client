<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/3
 * Time: 下午2:02
 */

namespace lanzhi\http;


use Generator;
use lanzhi\socket\ConnectionInterface;
use lanzhi\socket\connection;
use lanzhi\socket\Connector;
use lanzhi\socket\ConnectorInterface;
use Psr\Http\Message\ResponseInterface;
use lanzhi\coroutine\AbstractTaskUnit;
use Psr\Http\Message\RequestInterface;
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
     * @var array
     */
    private $options;
    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * RequestTaskUnit constructor.
     * @param RequestInterface $request
     * @param ConnectorInterface $connection
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(RequestInterface $request, ConnectorInterface $connector, array $options=[], LoggerInterface $logger=null)
    {
        $this->request   = $request;
        $this->connector = $connector;
        $this->options   = $options;
        $this->logger    = $logger ?? new NullLogger();

        parent::__construct($logger);
    }

    /**
     * @return Generator
     */
    protected function generate(): Generator
    {
        list($scheme, $host, $port) = Connector::parseUri($this->request->getUri());
        $connection = $this->connector->get($scheme, $host, $port, $this->options);

        $data = (new StreamBuilder($this->request))->build()->getContents();
        yield from $connection->write($data, true);

        $handle = new ReadHandler($this->logger);
        yield from $connection->read($handle);

        //连接使用过之后，归还连接器
        $this->connector->back($connection);

        $builder = new ResponseBuilder(
            $handle->getStartLine(),
            $handle->getHeader(),
            $handle->getBody()
        );
        return $builder->build();
    }



}