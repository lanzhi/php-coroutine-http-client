<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/4
 * Time: 下午2:20
 */

namespace lanzhi\http\exceptions;


class SocketException extends \Exception
{
    /**
     * SocketException constructor.
     * @param resource $socket
     * @param string   $error
     * @param int      $code
     * @param \Exception|null $previous
     */
    public function __construct($socket, $error=null, $code = 0, \Exception $previous = null)
    {
        socket_getsockname($socket, $host, $port);
        $error = $error ?? socket_strerror(socket_last_error($socket));
        socket_close($socket);

        $message = "host:{$host}; port:{$port}; error:{$error}";
        parent::__construct($message, $code, $previous);
    }
}