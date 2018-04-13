<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/4
 * Time: 上午10:22
 */

namespace lanzhi\http\exceptions;


use Exception;

class ConnectException extends \Exception
{
    const TYPE_HTTP  = 'http';
    const TYPE_REDIS = 'redis';

    /**
     * ConnectException constructor.
     * @param string $type
     * @param int    $host
     * @param int    $port
     * @param string $error
     * @param int    $code
     * @param Exception|null $previous
     */
    public function __construct($type, $host, $port, $error="", $code=0, Exception $previous = null)
    {
        $message = sprintf("type:%s; host:%s; port:%s; message:%s;", $type, $host, $port, $error);
        parent::__construct($message, $code, $previous);
    }
}
