<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/13
 * Time: 下午3:31
 */

namespace lanzhi\http;


use Generator;

interface ConnectionInterface
{
    const STATUS_NOT_READY = 'not-ready';
    const STATUS_CONNECTED = 'connected';
    const STATUS_CLOSED    = 'closed';

    const SOCKET_UNAVAILABLE = 'unavailable';
    const SOCKET_WRITABLE    = 'writable';
    const SOCKET_READABLE    = 'readable';

    /**
     * 区别连接池
     * @param string $name
     */
    public function setName(string $name):void;

    public function connect():Generator;
    public function write(string $data, bool $isEnd=false):Generator;
    public function read(ReadHandlerInterface $handler):Generator;
    public function close();

    public function getName():string;
    public function getStatus():string;
    public function getSocketStatus():string;

    public function isAvailable():bool;
}
