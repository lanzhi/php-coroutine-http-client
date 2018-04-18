<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/2
 * Time: 下午8:59
 */

namespace lanzhi\http;


use lanzhi\coroutine\TaskUnitInterface;
use lanzhi\http\exceptions\HttpException;
use lanzhi\http\exceptions\InvalidArgumentException;
use lanzhi\http\exceptions\UnsupportedException;
use lanzhi\socket\Connector;
use lanzhi\socket\ConnectorInterface;
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
 * @method RequestTaskUnit get(string|UriInterface $uri,  array $options = [])
 * @method RequestTaskUnit post(string|UriInterface $uri, array $options = [])
 */
class Client
{
    const VERSION     = '0.0.1';

    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';

    /**
     * @var array
     */
    private $defaultOptions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Client constructor.
     * @param array $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(array $defaultOptions = [], LoggerInterface $logger=null)
    {
        if (isset($defaultOptions['base_uri'])) {
            $defaultOptions['base_uri'] = \GuzzleHttp\Psr7\uri_for($defaultOptions['base_uri']);
        }

        $this->defaultOptions = $defaultOptions;
        $this->logger         = $logger ?? new NullLogger();
    }

    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $method = strtoupper($method);
        //当前只支持两种请求 GET、POST
        switch ($method){
            case self::METHOD_GET:
            case self::METHOD_POST:
                $uri  = $args[0];
                $opts = isset($args[1]) ? $args[1] : [];
                return $this->request($method, $uri, $opts);
            default:
                throw new UnsupportedException("unsupported now; method:{$method}");
        }
    }

    /**
     * @param string $method
     * @param UriInterface|string $uri
     * @param array $options
     * @return TaskUnitInterface
     */
    public function request($method, $uri, array $options = []): TaskUnitInterface
    {
        $builder = new RequestBuilder($method, $uri, $this->defaultOptions + $options);
        $request = $builder->build();

        if($request->getUri()->getScheme()==='https'){
            throw new UnsupportedException("unsupported now; scheme:https");
        }

        $connector      = $this->getConnector($options);
        $allowRedirects = $this->getAllowRedirects($options);
        return new RequestTaskUnit($request, $connector, $allowRedirects, $this->logger);
    }

    /**
     * @param null $option
     * @return array
     */
    public function getDefaultOptions($option = null)
    {
        if(empty($option)){
            return $this->defaultOptions;
        }elseif(isset($this->defaultOptions[$option])){
            return $this->defaultOptions[$option];
        }else{
            throw new HttpException("get unknown default option; option:{$option}");
        }
    }

    /**
     * @var ConnectorInterface
     */
    private $connector;
    private function getConnector(array $options)
    {
        if(!$this->connector){
            $connectOptions = $this->getConnectOptions($options);
            $this->connector = new Connector($connectOptions, $this->logger);
        }

        return $this->connector;
    }

    /**
     * 获取与连接有关的选项信息
     * @param array $options
     * @return array
     */
    private function getConnectOptions(array $options)
    {
        $timeout = [];
        $timeout['connect'] = $options[Options::CONNECT_TIMEOUT] ?? 3;
        $timeout['write']   = $options[Options::WRITE_TIMEOUT]   ?? 10;
        $timeout['read']    = $options[Options::READ_TIMEOUT]    ?? 30;

        return ['timeout'=>$timeout];
    }

    private function getAllowRedirects(array $options)
    {
        $allowRedirects = isset($options[Options::ALLOW_REDIRECTS]) ? $options[Options::ALLOW_REDIRECTS] : 3;
        $allowRedirects = $allowRedirects<=0 ? 0  : $allowRedirects;
        $allowRedirects = $allowRedirects>10 ? 10 : $allowRedirects;

        return $allowRedirects;
    }

}