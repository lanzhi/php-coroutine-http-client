<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/3
 * Time: 下午3:11
 */

namespace lanzhi\http;


use lanzhi\coroutine\TaskUnitInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Interface ClientInterface
 * @package lanzhi\coroutine\http
 *
 * 同步部分基于 guzzlehttp 实现
 * 异步部分基于 socket 实现
 *
 * API 基本与 guzzlehttp 保持一致
 */
interface ClientInterface
{
    const VERSION = '0.0.1';

    /**
     * Create and send an asynchronous HTTP request.
     *
     * Use an absolute path to override the base path of the client, or a
     * relative path to append to the base path of the client. The URL can
     * contain the query string as well. Use an array to provide a URL
     * template and additional variables to use in the URL template expansion.
     *
     * @param string              $method  HTTP method
     * @param string|UriInterface $uri     URI object or string.
     * @param array               $options Request options to apply.
     *
     * @return TaskUnitInterface
     */
    public function request($method, $uri, array $options = []): TaskUnitInterface;

    /**
     * Get a client configuration option.
     *
     * These options include default request options of the client, a "handler"
     * (if utilized by the concrete client), and a "base_uri" if utilized by
     * the concrete client.
     *
     * @param string|null $option The config option to retrieve.
     *
     * @return mixed
     */
    public function getConfig($option = null);
}