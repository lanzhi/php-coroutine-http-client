<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/9
 * Time: 上午11:15
 */

namespace lanzhi\http;


use lanzhi\http\exceptions\SocketConnectException;
use lanzhi\http\exceptions\SocketReadException;
use lanzhi\http\exceptions\SocketWriteException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Connector
 * @package lanzhi\http
 */
class Connector
{
    const CONNECT_TIMEOUT = 3;
    const REQUEST_TIMEOUT = 120;

    const HTTP_HEADER_DELIMITER = "\r\n\r\n";
    const HTTP_LINE_DELIMITER   = "\r\n";
    const HTTP_CHUNK_DELIMITER  = "\r\n";
    const HTTP_LAST_CHUNK_SIZE  = 0;

    const PROGRESS_FIRST_LINE = 1;
    const PROGRESS_HEADER     = 2;
    const PROGRESS_BODY       = 3;

    /**
     * @var UriInterface
     */
    private $uri;
    /**
     * @var int
     */
    private $connectTimeout;
    /**
     * @var resource
     */
    private $socket;
    /**
     * @var string
     */
    private $startLine;
    /**
     * @var StreamInterface
     */
    private $header;
    /**
     * @var StreamInterface
     */
    private $body;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Connector constructor.
     * @param UriInterface $uri
     * @param LoggerInterface|null $logger
     */
    public function __construct(UriInterface $uri, int $connectTimeout=null, LoggerInterface $logger=null)
    {
        $this->uri            = $uri;
        $this->connectTimeout = $connectTimeout ?? self::CONNECT_TIMEOUT;
        $this->logger         = $logger ?? new NullLogger();
    }


    /**
     * 建立连接
     * @return \Generator|void
     * @throws SocketConnectException
     */
    public function connect()
    {
        if($this->socket){
            return ;
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if(!socket_set_nonblock($socket)){
            throw new \Exception("set non-block fail");
        }

        if (!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            throw new \Exception('Unable to set option on socket: '. socket_strerror(socket_last_error()));
//            echo 'Unable to set option on socket: '. socket_strerror(socket_last_error()) . PHP_EOL;
        }

        $host = $this->uri->getHost();
        $port = $this->getPort($this->uri);

        $address = gethostbyname($host);
        $this->logger->debug("connect preparing; host:{host}, port:{port}, address:{address}", [
            'host'   => $host,
            'port'   => $port,
            'address'=> $address,
        ]);

        $yields = 0;
        $startTime = microtime(true);
        //因为非阻塞，当时无法连接并不能认为是错误
        if(!socket_connect($socket, $address, $port)){
            $errorNo = socket_last_error($socket);
            if($errorNo===SOCKET_EINPROGRESS){
                //判断连接超时
                while(true){
                    if(microtime(true)-$startTime > self::CONNECT_TIMEOUT){
                        throw new SocketConnectException($socket, "connect timeout; timeout:{$this->connectTimeout}");
                    }
                    $reads  = [];
                    $writes = [$socket];
                    $excepts= [];
                    $changes = socket_select($reads, $writes, $excepts, 0);

                    if($changes===false){
                        throw new SocketConnectException($socket);
                    }elseif($changes===1){
                        break;
                    }else{
                        $yields++;
                        yield;
                    }
                }
            }else{
                throw new SocketConnectException($socket);
            }
        }

        $this->logger->debug("connect successfully; time usage:{timeUsage}; yield times:{yield}", [
            'timeUsage' => round(microtime(true)-$startTime, 6),
            'yield'     => $yields
        ]);

        $this->socket = $socket;
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return \Generator
     */
    public function request(RequestInterface $request)
    {
        yield from $this->connect();

        $builder = new StreamBuilder($request);
        $stream  = $builder->build();

        yield from $this->write($stream);
        yield from $this->read();

        return [$this->startLine, $this->header, $this->body];
    }


    /**
     * @param StreamInterface $stream
     * @return \Generator
     * @throws SocketWriteException
     */
    private function write(StreamInterface $stream)
    {
        $data = $stream->getContents();
        $this->logger->debug($data);

        $startTime = microtime(true);
        $length  = strlen($data);
        $written = 0;
        $yields  = 0;

        while(true){
            //已写入完毕
            if($written==$length){
                break;
            }

            if(microtime(true)-$startTime > self::REQUEST_TIMEOUT){
                throw new SocketWriteException($this->socket, "request timeout; timeout:".self::REQUEST_TIMEOUT);
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
    }

    /**
     * @return \Generator
     * @throws SocketReadException
     */
    private function read()
    {
        $startTime = microtime(true);

        $startLine = '';
        $header    = '';
        $body      = '';

        $yields        = 0;
        $contentLength = null;
        $isChunked     = false;
        $contentEncode = null;

        $buffer = '';
        $size   = 4096;
        $progress = self::PROGRESS_FIRST_LINE;
        while (true){
            if(microtime(true)-$startTime > self::REQUEST_TIMEOUT){
                throw new SocketReadException($this->socket, "request timeout; timeout:".self::REQUEST_TIMEOUT);
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
                if($chunk===false && socket_last_error($this->socket)!==SOCKET_EAGAIN){
                    throw new SocketReadException($this->socket);
                }elseif($chunk){
                    $buffer .= $chunk;

                    switch ($progress){
                        case self::PROGRESS_FIRST_LINE:
                            if(strpos($buffer, self::HTTP_LINE_DELIMITER)!==false){
                                list($startLine, $buffer) = explode(self::HTTP_LINE_DELIMITER, $buffer, 2);
                                $progress = self::PROGRESS_HEADER;
                            }
                            break;
                        case self::PROGRESS_HEADER:
                            if(strpos($buffer, self::HTTP_HEADER_DELIMITER)!==false){
                                list($header, $buffer) = explode(self::HTTP_HEADER_DELIMITER, $buffer, 2);
                                list($contentLength, $isChunked, $contentEncode) = $this->getUnpackMetaInfoFromHeader($header);
                                if(!$isChunked){
                                    $size = $contentLength - strlen($buffer);
                                }
                                $progress = self::PROGRESS_BODY;
                            }
                            break;
                        case self::PROGRESS_BODY:
                            if($isChunked){
                                $buffer = $this->unpackWhenChunked($buffer, $size);
                            }
                            break;
                    }
                }
            }

            //http 报文结束
            if(
                ($isChunked     && $size===self::HTTP_LAST_CHUNK_SIZE) ||
                ($contentLength && strlen($buffer)>=$contentLength)
            ){
                $body = $contentLength ? substr($buffer, 0, $contentLength) : $buffer;
                break;
            }

            $yields++;
            yield;
        }

        $this->logger->debug("read successfully; time usage:{timeUsage}; yield times:{yield}; body size:{size}", [
            'timeUsage' => round(microtime(true)-$startTime, 6),
            'yield'     => $yields,
            'size'      => strlen($body)
        ]);

        $this->startLine = $startLine;
        $this->header    = $header;
        $this->body      = $body;
        return ;
    }

    /**
     * @param UriInterface $uri
     * @return int|mixed|null
     */
    private function getPort(UriInterface $uri)
    {
        $defaults = [
            'http'  => 80,
            'https' => 443,
        ];
        $port = $uri->getPort();
        $port = $port ?? $defaults[$uri->getScheme()];

        return $port;
    }

    /**
     * @param string $header
     * @return array
     */
    private function getUnpackMetaInfoFromHeader(string $header)
    {
        $contentLength = null;
        $isChunked     = false;
        $contentEncode = null;

        $headers = explode(self::HTTP_LINE_DELIMITER, $header);
        foreach ($headers as $header){
            $header = strtolower($header);
            if(strpos($header, 'content-length')!==false){
                list(, $contentLength) = explode(':', $header);
            }
            if(strpos($header, 'transfer-encoding')!==false && strpos($header, 'chunked')!==false){
                $isChunked = true;
            }
            if(strpos($header, 'content-encoding')){
                list(, $contentEncode) = explode(':', $header);
            }
        }

        $contentLength = $isChunked ? null : trim($contentLength);
        $contentEncode = trim($contentEncode);

        return [$contentLength, $isChunked, $contentEncode];
    }

    /**
     * @param string $buffer
     * @param $size
     * @return string
     */
    private function unpackWhenChunked(string $buffer, &$size)
    {
        $bodyParts = [];

        $list = explode(self::HTTP_CHUNK_DELIMITER, $buffer);
        foreach ($list as $chunk){
            if(!$this->isChunkHead($chunk, $size)){
                $bodyParts[] = $chunk;
            }

            if($size==self::HTTP_LAST_CHUNK_SIZE){
                break;
            }
        }

        return implode('', $bodyParts);
    }

    /**
     * 是否为块的首行
     * @param string $chunk
     * @param int $size 如果size为0，则说明body结束
     * @return bool
     */
    private function isChunkHead(string $chunk, int &$size)
    {
        $hex = "0x".$chunk;
        $dec = hexdec($hex);

        if($chunk==dechex($dec)){
            $size = $dec;
            return true;
        }else{
            return false;
        }
    }

    /**
     * 关闭连接
     */
    public function __destruct()
    {
        if(!$this->socket){
            return ;
        }
        socket_close($this->socket);
    }
}
