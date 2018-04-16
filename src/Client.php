<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/2
 * Time: 下午8:59
 */

namespace lanzhi\http;


use lanzhi\coroutine\TaskUnitInterface;
use lanzhi\socket\Connector;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Client
 * @package lanzhi\http
 *
 * 该客户端 API 参照 guzzlehttp，并最大限度与其保持一致，不同如下：
 * 不支持 send 及 send API
 * 其它  API 均返回 TaskUnitInterface 类型
 *
 * 此外 options 不支持如下选项：
 * handler
 *
 * @method RequestTaskUnit get(string|UriInterface $uri,    array $options = [])
 * @method RequestTaskUnit head(string|UriInterface $uri,   array $options = [])
 * @method RequestTaskUnit put(string|UriInterface $uri,    array $options = [])
 * @method RequestTaskUnit post(string|UriInterface $uri,   array $options = [])
 * @method RequestTaskUnit patch(string|UriInterface $uri,  array $options = [])
 * @method RequestTaskUnit delete(string|UriInterface $uri, array $options = [])
 */
class Client
{
    const VERSION = '0.0.1';
    /** @var array Default request options */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Client constructor.
     * @param array $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $config = [], LoggerInterface $logger=null)
    {
        if (isset($config['base_uri'])) {
            $config['base_uri'] = \GuzzleHttp\Psr7\uri_for($config['base_uri']);
        }

        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
    }

    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri  = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];

        return $this->request($method, $uri, $opts);
    }

    /**
     * @param string $method
     * @param UriInterface|string $uri
     * @param array $options
     * @return TaskUnitInterface
     */
    public function request($method, $uri, array $options = []): TaskUnitInterface
    {
        $builder = new RequestBuilder($method, $uri, $this->config + $options);
        $request = $builder->build();

        $connectOptions = $this->getConnectOptions($options);

        return new RequestTaskUnit(
            $request,
            new Connector($this->logger),
            $connectOptions,
            $this->logger
        );
    }

    /**
     * @param null $option
     * @return array
     */
    public function getConfig($option = null)
    {
        return $this->config;
    }

    /**
     * 获取与连接有关的选项信息
     * @param array $options
     * @return array
     */
    private function getConnectOptions(array $options)
    {
        $timeout = [];
        if(isset($options[Options::CONNECT_TIMEOUT])){
            $timeout['connect'] = $options[Options::CONNECT_TIMEOUT];
        }
        if(isset($options[Options::WRITE_TIMEOUT])){
            $timeout['write'] = $options[Options::WRITE_TIMEOUT];
        }
        if(isset($options[Options::READ_TIMEOUT])){
            $timeout['read'] = $options[Options::READ_TIMEOUT];
        }

        return ['timeout'=>$timeout];
    }

}