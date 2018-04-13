<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 下午7:28
 */

namespace lanzhi\http;


/**
 * Class RequestOptions
 * @package lanzhi\http
 */
class RequestOptions
{
    /**
     * body: (resource|string|null|int|float|StreamInterface|callable|\Iterator)
     * Body to send in the request.
     */
    const BODY = 'body';

    /**
     * connect_timeout: (float, default=0) Float describing the number of
     * seconds to wait while trying to connect to a server. Use 0 to wait
     * indefinitely (the default behavior).
     */
    const CONNECT_TIMEOUT = 'connect_timeout';

    /**
     * form_params: (array) Associative array of form field names to values
     * where each value is a string or array of strings. Sets the Content-Type
     * header to application/x-www-form-urlencoded when no Content-Type header
     * is already present.
     */
    const FORM_PARAMS = 'form_params';

    /**
     * headers: (array) Associative array of HTTP headers. Each value MUST be
     * a string or array of strings.
     */
    const HEADERS = 'headers';

    /**
     * json: (mixed) Adds JSON data to a request. The provided value is JSON
     * encoded and a Content-Type header of application/json will be added to
     * the request if no Content-Type header is already present.
     */
    const JSON = 'json';

    /**
     * multipart: (array) Array of associative arrays, each containing a
     * required "name" key mapping to the form field, name, a required
     * "contents" key mapping to a StreamInterface|resource|string, an
     * optional "headers" associative array of custom headers, and an
     * optional "filename" key mapping to a string to send as the filename in
     * the part. If no "filename" key is present, then no "filename" attribute
     * will be added to the part.
     */
    const MULTIPART = 'multipart';

    /**
     * query: (array|string) Associative array of query string values to add
     * to the request. This option uses PHP's http_build_query() to create
     * the string representation. Pass a string value if you need more
     * control than what this method provides
     */
    const QUERY = 'query';


//    const ALLOW_REDIRECTS = 'allow_redirects';
//    const AUTH = 'auth';
//    const CERT = 'cert';
//    const COOKIES = 'cookies';
//    const DEBUG = 'debug';
//    const DECODE_CONTENT = 'decode_content';
//    const DELAY = 'delay';
//    const EXPECT = 'expect';
//    const HTTP_ERRORS = 'http_errors';
//    const ON_HEADERS = 'on_headers';
//    const ON_STATS = 'on_stats';
//    const PROGRESS = 'progress';
//    const PROXY = 'proxy';
//    const SINK = 'sink';
//    const SYNCHRONOUS = 'synchronous';
//    const SSL_KEY = 'ssl_key';
//    const STREAM = 'stream';
//    const VERIFY = 'verify';
//    const TIMEOUT = 'timeout';
//    const READ_TIMEOUT = 'read_timeout';
//    const VERSION = 'version';
//    const FORCE_IP_RESOLVE = 'force_ip_resolve';
}