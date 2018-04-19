<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 上午11:16
 */

namespace lanzhi\http;


use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\RequestInterface;

/**
 * Class RequestBuilder
 * @package lanzhi\http
 */
class RequestBuilder
{
    /**
     * @var string
     */
    private $method;
    /**
     * @var string
     */
    private $uri;
    /**
     * @var array
     */
    private $options;

    public function __construct(string $method, string $uri, array $options=[])
    {
        $this->method = $method;
        $this->uri    = $uri;
        $this->options= $options;
    }

    public function build():RequestInterface
    {
        $options = $this->reviewOptions($this->options);
        $uri     = $this->reviewUri($this->uri, $options);

        $request = new Request($this->method, $uri, $options['headers'], $options['body'], $options['version']);
        unset($options['headers'], $options['body'], $options['version']);

        return $this->applyOptions($request, $options);
    }

    /**
     * 检查options，为部分 header 提供默认选项
     * @param array $options
     * @return array
     */
    private function reviewOptions(array &$options)
    {
        $options['headers'] = isset($options['headers']) ? $options['headers'] : [];
        $options['body']    = isset($options['body'])    ? $options['body']    : null;
        $options['version'] = '1.1';

        if(empty($options['headers'])){
            $options['headers'] = ['User-Agent'=>$this->defaultUserAgent()];
        }else{
            $hasUserAgent = false;
            foreach ($options['headers'] as $name=>$value){
                if(strtolower($name)=='user-agent'){
                    $hasUserAgent = true;
                    break;
                }
            }
            if($hasUserAgent){
                $options['headers']['User-Agent'] = $this->defaultUserAgent();
            }
        }

        $options['headers']['Accept']          = $options['headers']['Accept'] ?? "*/*";
        $options['headers']['Accept-Encoding'] = 'identity';//当前不支持 gzip、deflate 等内容编码方式

        return $options;
    }

    private function reviewUri(string $uri, array $config)
    {
        if(strpos($uri, "http://")!==0 && strpos($uri, "https://")!==0){
            $uri = "http://{$uri}";
        }

        $uri = \GuzzleHttp\Psr7\uri_for($uri === null ? '' : $uri);
        if (isset($config['base_uri'])) {
            $uri = UriResolver::resolve(\GuzzleHttp\Psr7\uri_for($config['base_uri']), $uri);
        }

        return $uri;
    }

    /**
     *
     * @param RequestInterface $request
     * @param array $options
     * @return RequestInterface
     * @throws \Exception
     */
    private function applyOptions(RequestInterface $request, array &$options)
    {
        $modify = [
            'set_headers' => [],
        ];

        $options['_conditional'] = $options['_conditional'] ?? [];
        if (isset($options['form_params'])) {
            if (isset($options['multipart'])) {
                throw new \InvalidArgumentException('You cannot use '
                    . 'form_params and multipart at the same time. Use the '
                    . 'form_params option if you want to send application/'
                    . 'x-www-form-urlencoded requests, and the multipart '
                    . 'option to send multipart/form-data requests.');
            }
            $options['body'] = http_build_query($options['form_params'], '', '&');
            unset($options['form_params']);

            $options['_conditional'] = $this->clearCaseInsensitive('Content-Type', $options['_conditional']);
            $options['_conditional']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if (isset($options['multipart'])) {
            $options['body'] = new Psr7\MultipartStream($options['multipart']);
            unset($options['multipart']);
        }

        if (isset($options['json'])) {
            $options['body'] = \GuzzleHttp\json_encode($options['json']);
            unset($options['json']);

            $options['_conditional'] = $this->clearCaseInsensitive('Content-Type', $options['_conditional']);
            $options['_conditional']['Content-Type'] = 'application/json';
        }

        if (!empty($options['decode_content'])
            && $options['decode_content'] !== true
        ) {
            // Ensure that we don't have the header in different case and set the new value.
            $options['_conditional'] = $this->clearCaseInsensitive('Accept-Encoding', $modify['set_headers']);
            $modify['set_headers']['Accept-Encoding'] = $options['decode_content'];
        }

        if (isset($options['body'])) {
            if (is_array($options['body'])) {
                throw new \Exception("body must be string");
            }
            $modify['body'] = Psr7\stream_for($options['body']);
            unset($options['body']);
        }

        if (!empty($options['query'])) {
            $value = $options['query'];
            if (is_array($value)) {
                $value = http_build_query($value, null, '&', PHP_QUERY_RFC3986);
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException('query must be a string or array');
            }
            $modify['query'] = $value;
            unset($options['query']);
        }

        $request = Psr7\modify_request($request, $modify);
        if ($request->getBody() instanceof Psr7\MultipartStream) {
            $options['_conditional'] = $this->clearCaseInsensitive('Content-Type', $options['_conditional']);
            $options['_conditional']['Content-Type'] = 'multipart/form-data; boundary='
                . $request->getBody()->getBoundary();
        }

        // Merge in conditional headers if they are not present.
        if (isset($options['_conditional'])) {
            // Build up the changes so it's in a single clone of the message.
            $modify = [];
            foreach ($options['_conditional'] as $k => $v) {
                if (!$request->hasHeader($k)) {
                    $modify['set_headers'][$k] = $v;
                }
            }
            $request = Psr7\modify_request($request, $modify);
            // Don't pass this internal value along to middleware/handlers.
            unset($options['_conditional']);
        }

        return $request->withHeader('Content-Length', $request->getBody()->getSize());
    }

    /**
     * @param string $name
     * @param array $list
     * @return array
     */
    private function clearCaseInsensitive(string $name, array $list)
    {
        $name = strtolower($name);
        foreach ($list as $key=>$item){
            if(strtolower($key)==$name){
                unset($list[$key]);
            }
        }

        return $list;
    }

    /**
     * @return string
     */
    private function defaultUserAgent()
    {
        return 'lanzhi/php-sync-http-client, version:'.Client::VERSION;
    }

}