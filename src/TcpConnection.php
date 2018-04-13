<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/13
 * Time: 上午11:32
 */

namespace lanzhi\http;

use lanzhi\http\exceptions\SocketConnectException;
use lanzhi\http\exceptions\SocketReadException;
use lanzhi\http\exceptions\SocketWriteException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TcpConnection implements ConnectionInterface
{
    const TIMEOUT_CONNECT = 3;
    const TIMEOUT_WRITE   = 60;
    const TIMEOUT_READ    = 60;

    const BUFFER_SIZE      = 65536;//64k

    private $name;
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $host;
    private $port;

    private $timeoutConnect;
    private $timeoutWrite;
    private $timeoutRead;

    /**
     * @var resource
     */
    private $socket;
    /**
     * @var string
     */
    private $status;

    private $socketStatus;

    /**
     * Connection constructor.
     * @param string $ip
     * @param int $port
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $ip, int $port, array $options=[], LoggerInterface $logger=null)
    {
        $this->host           = $ip;
        $this->port           = $port;
        $this->timeoutConnect = $options['timeout']['connect'] ?? self::TIMEOUT_CONNECT;
        $this->timeoutWrite   = $options['timeout']['write']   ?? self::TIMEOUT_WRITE;
        $this->timeoutRead    = $options['timeout']['read']    ?? self::TIMEOUT_READ;
        $this->logger         = $logger                        ?? new NullLogger();

        $this->status       = self::STATUS_NOT_READY;
        $this->socketStatus = self::SOCKET_UNAVAILABLE;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getStatus():string
    {
        return $this->status;
    }

    public function getSocketStatus():string
    {
        return $this->socketStatus;
    }

    public function isAvailable(): bool
    {
        return $this->status===self::STATUS_CONNECTED;
    }

    /**
     * @return \Generator
     * @throws SocketConnectException
     * @throws \Exception
     */
    public function connect(): \Generator
    {
        if($this->status===self::STATUS_CONNECTED){
            return ;
        }
        if($this->status===self::STATUS_CLOSED){
            throw new \Exception("connection has been closed; get a new one by connector");
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!socket_set_nonblock($socket)){
            throw new \Exception("set non-block fail");
        }

        if (!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new \Exception('Unable to set option on socket: '. socket_strerror(socket_last_error()));
        }

        $yields = 0;
        $startTime = microtime(true);
        //因为非阻塞，当时无法连接并不能认为是错误
        if(!socket_connect($socket, $this->host, $this->port)){
            $errorNo = socket_last_error($socket);
            if($errorNo===SOCKET_EINPROGRESS){
                //判断连接超时
                while(true){
                    if(microtime(true)-$startTime > $this->timeoutConnect){
                        throw new SocketConnectException($socket, "connect timeout; timeout:{$this->timeoutConnect}");
                    }
                    $reads  = [];
                    $writes = [$socket];
                    $excepts= [];
                    $changes = socket_select($reads, $writes, $excepts, 0);

                    if($changes===false){
                        throw new SocketConnectException($socket);
                    }elseif($changes===1){
                        break;
                    }

                    $yields++;
                    yield;
                }
            }else{
                throw new SocketConnectException($socket);
            }
        }

        $this->logger->debug("connect successfully; host:{host}:{port}; time usage:{timeUsage}; yield times:{yield}", [
            'host'      => $this->host,
            'port'      => $this->port,
            'timeUsage' => round(microtime(true)-$startTime, 6),
            'yield'     => $yields
        ]);

        $this->socket       = $socket;
        $this->status       = self::STATUS_CONNECTED;
        $this->socketStatus = self::SOCKET_WRITABLE;
    }

    /**
     * @param string $data
     * @param bool $isEnd
     * @return \Generator
     * @throws SocketWriteException
     * @throws \Exception
     */
    public function write(string $data=null, bool $isEnd=false):\Generator
    {
        if($this->status===self::STATUS_NOT_READY){
            yield from $this->connect();
        }

        if($this->socketStatus!==self::SOCKET_WRITABLE){
            throw new \Exception("connection not writable now; status:{$this->status}");
        }

        $this->logger->debug("request header\n".$data);
        $startTime = microtime(true);
        $length    = strlen($data);
        $written   = 0;
        $yields    = 0;

        while(true){
            //写入完毕
            if($written==$length){
                break;
            }

            if(microtime(true)-$startTime > $this->timeoutWrite){
                throw new SocketWriteException($this->socket, "write timeout; timeout:{$this->timeoutWrite}");
            }

            $reads  = [];
            $writes = [$this->socket];
            $excepts= [];
            $changes = socket_select($reads, $writes, $excepts, 0);

            if($changes===false){
                throw new SocketWriteException($this->socket);
            }elseif($changes>0){
                //此时可写
                $size = socket_write($this->socket, substr($data, $written), 4096);
                if($size===false && socket_last_error($this->socket)!==SOCKET_EAGAIN){
                    throw new SocketWriteException($this->socket);
                }elseif($size>0){
                    $written += $size;
                }
            }

            $yields++;
            yield;
        }

        $this->logger->debug("write successfully; time usage:{timeUsage}; yield times:{yield}; data length:{length}", [
            'timeUsage' => round(microtime(true)-$startTime, 6),
            'yield'     => $yields,
            'length'    => strlen($data)
        ]);

        //如果写结束，则变更状态
        if($isEnd){
            $this->socketStatus = self::SOCKET_READABLE;
        }
    }

    /**
     * 从连接中读取数据
     * @param ReadHandlerInterface $handle
     * @return \Generator
     * @throws SocketReadException
     * @throws \Exception
     */
    public function read(ReadHandlerInterface $handle):\Generator
    {
        if($this->socketStatus!==self::SOCKET_READABLE){
            throw new \Exception("connection not readable now; status:{$this->status}");
        }

        $startTime = microtime(true);
        $buffer = null;
        $size   = self::BUFFER_SIZE;
        $isEnd  = false;
        $shouldClose = false;

        $yields = 0;
        while (true){
            if(microtime(true)-$startTime > $this->timeoutRead){
                throw new SocketReadException($this->socket, "request timeout; timeout:{$this->timeoutRead}");
            }

            $reads  = [$this->socket];
            $writes = [];
            $excepts= [];
            $changes = socket_select($reads, $writes, $excepts, 0);
            if($changes===false){
                throw new SocketReadException($this->socket);
            }elseif($changes>0){
                //此时可读
                $chunk = socket_read($this->socket, $size);
                if($chunk===false){
                    $no = socket_last_error($this->socket);
                    if($no!==SOCKET_EAGAIN && $no!==SOCKET_EINTR){
                        throw new SocketReadException($this->socket);
                    }
                }else{
                    $buffer .= $chunk;
                    $handle->deal($buffer, $size, $isEnd, $shouldClose);
                }
            }

            if($isEnd){
                break;
            }

            $yields++;
            yield;
        }

        $this->logger->debug("read successfully; time usage:{timeUsage}; yield times:{yield};", [
            'timeUsage' => round(microtime(true)-$startTime, 6),
            'yield'     => $yields
        ]);

        if($isEnd){
            $this->socketStatus = self::SOCKET_WRITABLE;
        }

        if($shouldClose){
            $this->close();
        }
    }

    public function close()
    {
        if($this->status===self::STATUS_CONNECTED){
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->status       = self::STATUS_CLOSED;
        $this->socketStatus = self::SOCKET_UNAVAILABLE;
    }

    public function __destruct()
    {
        if($this->socket){
            socket_close($this->socket);
        }
    }
}