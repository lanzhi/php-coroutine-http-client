<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/13
 * Time: 下午4:30
 */

namespace lanzhi\http;


interface ConnectorInterface
{
    const SCHEME_TCP  = 'tcp';
    const SCHEME_SSL  = 'ssl';
    const SCHEME_UNIX = 'unix';

    /**
     * 获取连接
     * @param $uri
     * @return mixed
     */
    public function get(string $scheme, string $host, int $port):ConnectionInterface;

    /**
     * 归还连接
     * 如果此时连接已经关闭，则销毁
     * @param ConnectionInterface $connection
     */
    public function back(ConnectionInterface $connection):void;
}