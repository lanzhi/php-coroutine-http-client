<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/2
 * Time: 下午8:59
 */

namespace lanzhi\http;


use lanzhi\coroutine\TaskUnitInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

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
 * @method TaskUnit get(string|UriInterface $uri,    array $options = [])
 * @method TaskUnit head(string|UriInterface $uri,   array $options = [])
 * @method TaskUnit put(string|UriInterface $uri,    array $options = [])
 * @method TaskUnit post(string|UriInterface $uri,   array $options = [])
 * @method TaskUnit patch(string|UriInterface $uri,  array $options = [])
 * @method TaskUnit delete(string|UriInterface $uri, array $options = [])
 */
class Client implements ClientInterface
{
    /** @var array Default request options */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param array $config Client configuration settings.
     *
     * @see \GuzzleHttp\RequestOptions for a list of available request options.
     */
    public function __construct(array $config = [], LoggerInterface $logger=null)
    {
        // Convert the base_uri to a UriInterface
        if (isset($config['base_uri'])) {
            $config['base_uri'] = \GuzzleHttp\Psr7\uri_for($config['base_uri']);
        }

        $this->config = $config;
        $this->logger = $logger;
    }

    public function __call($method, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $args[0];
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

        $timeout = $options['connect_timeout'] ?? null;
        $connector = new Connector($request->getUri(), $timeout, $this->logger);

        return new TaskUnit($request, $connector, function ($startLine, $header, $body){
            $builder = new ResponseBuilder($startLine, $header, $body);
            return $builder->build();
        }, $this->logger);
    }

    /**
     * @param null $option
     * @return array
     */
    public function getConfig($option = null)
    {
        return $this->config;
    }

}