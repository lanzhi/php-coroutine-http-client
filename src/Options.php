<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 下午7:28
 */

namespace lanzhi\http;

use Psr\Http\Message\StreamInterface;


/**
 * Class RequestOptions
 * @package lanzhi\http
 */
class Options
{
    /**
     * 连接建立超时时间，浮点型，默认 3.0
     */
    const CONNECT_TIMEOUT = 'connect_timeout';
    /**
     * 向连接中写入数据的超时时间，浮点型，默认 10.0
     */
    const WRITE_TIMEOUT   = 'write_timeout';
    /**
     * 从连接中读取数据的超时时间，浮点型，默认 30.0
     */
    const READ_TIMEOUT    = 'read_timeout';

    /**
     * @var array
     * 数组类型，键为首部名称，应该遵循 HTTP 规范，大小写敏感，值可以为字符串或者数组
     */
    const HEADERS = 'headers';

    /**
     * @var int | float | string | resource | StreamInterface 默认null
     */
    const BODY = 'body';

    /**
     * @var array
     * 一旦提供该参数，则会提供默认 Content-Type: application/json 首部
     */
    const JSON = 'json';

    /**
     * @var array 支持多维数组
     * 相当于提交 application/x-www-form-urlencoded 类型表单
     * 一旦提供该参数，则会提供默认 Content-Type: application/x-www-form-urlencoded 首部
     */
    const FORM_PARAMS = 'form_params';

    /**
     * @var array 支持多维数组，暂时不支持文件
     * 相当于提交 multipart/form-data 类型表单
     * 一旦提供该参数，则会提供默认 Content-Type: multipart/form-data 首部
     */
    const MULTIPART = 'multipart';

    /**
     * @var string | array
     */
    const QUERY = 'query';

    /**
     * @var false | int 默认 3，即单次请求最多允许重定向 3 次；该参数的有效范围为 0～10；false 即0
     */
    const ALLOW_REDIRECTS = 'allow_redirects';

    /**
     * 当前只支持 HTTP/1.1 版本协议，其它值均为无效
     */
    const VERSION = 'version';

//    const AUTH = 'auth';
//    const CERT = 'cert';
//    const COOKIES = 'cookies';
//    const PROXY = 'proxy';
//    const SINK = 'sink';
//    const SSL_KEY = 'ssl_key';
//    const VERIFY = 'verify';

}