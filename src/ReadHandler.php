<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/16
 * Time: 上午11:30
 */

namespace lanzhi\http;


use lanzhi\socket\ReadHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ReadHandler implements ReadHandlerInterface
{
    const PROCESS_START_LINE = 'start-line';
    const PROCESS_HEADER     = 'header';
    const PROCESS_BODY       = 'body';

    const HTTP_HEADER_DELIMITER = "\r\n\r\n";
    const HTTP_LINE_DELIMITER   = "\r\n";
    const HTTP_CHUNK_DELIMITER  = "\r\n";
    const HTTP_LAST_CHUNK_SIZE  = 0;

    private $startLine;
    private $headers;
    private $body;
    private $logger;


    private $process       = self::PROCESS_START_LINE;
    private $isChunked     = null;
    private $contentLength = false;
    private $contentEncode = false;
    private $shouldClose   = false;


    public function __construct(LoggerInterface $logger=null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getStartLine()
    {
        return $this->startLine;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function deal(string &$buffer, int &$size, bool &$isEnd = false, bool &$shouldClose = false): void
    {
        if(
            $this->process===self::PROCESS_START_LINE &&
            strpos($buffer, self::HTTP_LINE_DELIMITER)!==false
        ){
            list($this->startLine, $buffer) = explode(self::HTTP_LINE_DELIMITER, $buffer, 2);
            $this->process = self::PROCESS_HEADER;
        }

        if(
            $this->process===self::PROCESS_HEADER &&
            strpos($buffer, self::HTTP_HEADER_DELIMITER)!==false
        ){
            list($this->headers, $buffer) = explode(self::HTTP_HEADER_DELIMITER, $buffer, 2);

            $this->unpackMetaInfoFromHeaders();
            if(!$this->isChunked){
                $size = $this->contentLength - strlen($buffer);
            }
            $this->process = self::PROCESS_BODY;
        }

        if($this->process===self::PROCESS_BODY && $this->isChunked){
            $buffer = $this->unpackWhenChunked($buffer, $size);
        }

        //http 报文结束
        if(
            ($this->isChunked     && $size===self::HTTP_LAST_CHUNK_SIZE) ||
            ($this->contentLength && strlen($buffer)>=$this->contentLength)
        ){
            $this->body = $this->contentLength ? substr($buffer, 0, $this->contentLength) : $buffer;
            $isEnd       = true;
            $shouldClose = $this->shouldClose;
        }
    }

    /**
     *
     */
    private function unpackMetaInfoFromHeaders()
    {
        $this->isChunked     = false;
        $this->contentLength = null;
        $this->contentEncode = null;

        $lines = explode(self::HTTP_LINE_DELIMITER, $this->headers);
        foreach ($lines as $line){
            $line = strtolower($line);
            list($name, $value) = explode(':', $line);
            switch ($name){
                case 'content-length':
                    $this->contentLength = trim($value);
                    break;
                case 'content-encoding':
                    $this->contentEncode = trim($value);
                    break;
                case 'transfer-encoding':
                    if(trim($value)=='chunked'){
                        $this->isChunked = true;
                    }
                    break;
                case 'connection':
                    $items = explode(',', trim($value));
                    $items = array_map('trim', $items);
                    if(in_array('close', $items)){
                        $this->shouldClose = true;
                    }
            }
        }
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


}