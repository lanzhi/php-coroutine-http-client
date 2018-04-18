<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/18
 * Time: 下午12:03
 */

namespace lanzhi\http\exceptions;


use Exception;

class RedirectTooManyTimesException extends HttpException
{
    public function __construct($uri, $times, $code = 0, Exception $previous = null)
    {
        $message = "redirect too many times; times:{$times}; uri:{$uri}";
        parent::__construct($message, $code, $previous);
    }
}