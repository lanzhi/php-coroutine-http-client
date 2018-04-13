<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/3
 * Time: 下午2:02
 */

namespace lanzhi\http;


use Generator;
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
class TaskUnit extends AbstractTaskUnit
{
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var Connector
     */
    private $connector;
    /**
     * @var callable function($startLine, $header, $body)
     */
    private $builder;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * TaskUnit constructor.
     * @param RequestInterface $request
     * @param array $options
     */
    public function __construct(RequestInterface $request, Connector $connector, callable $responseBuilder, LoggerInterface $logger=null)
    {
        $this->request   = $request;
        $this->connector = $connector;
        $this->builder   = $responseBuilder;
        $this->logger    = $logger ?? new NullLogger();

        parent::__construct($logger);
    }

    /**
     * @return Generator
     */
    protected function generate(): Generator
    {
        yield from $this->connector->connect();

        $generator = $this->connector->request($this->request);
        yield from $generator;

        list($startLine, $header, $body) = $generator->getReturn();

        $builder = $this->builder;
        return $builder($startLine, $header, $body);
    }

}