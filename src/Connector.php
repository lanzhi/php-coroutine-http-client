<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/13
 * Time: 下午4:55
 */

namespace lanzhi\http;


use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Connector implements ConnectorInterface
{


    private $pool;

    private $busyConnections;
    private $idleConnections;

    /**
     * @var array
     * ```php
     * [
     *     'baidu.com' => [
     *         ['220.181.57.216', '111.13.101.208'],
     *         0
     *     ]
     * ]
     * ```
     */
    private static $dns = [];

    private static $defaultPorts = [
        'http'  => 80,
        'https' => 443,
        'redis' => 2222
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;
    public function __construct(LoggerInterface $logger=null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param $uri
     * @return [scheme, host, port]
     */
    public static function parseUri(string $uri)
    {
        $parts = parse_url($uri);
        $scheme = $parts['scheme'] ?? '';
        $host   = $parts['host']   ?? '';
        $port   = $parts['port']   ?? 0;

        if(empty($port) && isset(self::$defaultPorts[$scheme])){
            $port = self::$defaultPorts[$scheme];
        }

        return [$scheme, $host, $port];
    }

    /**
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @param array $options
     * @return ConnectionInterface
     */
    public function get(string $scheme, string $host, int $port, array $options=[]): ConnectionInterface
    {
        $name = $this->buildConnectionName($scheme, $host, $port);
        $connection = $this->getFromIdleQueue($name);
        if(!$connection){
            $connection = $this->buildOne($scheme, $host, $port, $options);
        }

        $this->addToBusyQueue($name, $connection);
        return $connection;
    }

    /**
     * @param ConnectionInterface $connection
     * @throws \Exception
     */
    public function back(ConnectionInterface $connection): void
    {
        $name = $connection->getName();
        if(empty($this->busyConnections[$name])){
            throw new \Exception("unknown connection A; may be not created by this connector");
        }

        $miss = true;
        foreach ($this->busyConnections[$name] as $key=>$item){
            if($connection===$item){
                unset($this->busyConnections[$name][$key]);
                $miss = false;
            }
        }
        if($miss){
            throw new \Exception("unknown connection B; may be not created by this connector");
        }

        if(!$connection->isAvailable()){
            unset($connection);
        }else{
            //追加到空闲队列
            $this->idleConnections[$name][] = $connection;
        }
    }

    private function buildOne(string $scheme, string $host, int $port, array $options=[])
    {
        if(empty($scheme) || empty($host) || empty($port)){
            throw new \Exception("scheme or host or port empty");
        }
        if(empty(self::$dns[$host])){
            $ips = gethostbynamel($host);
            if($ips===false){
                throw new \Exception("can't resolve host:{$host}");
            }
            self::$dns[$host] = [$ips, 0];
        }

        $ip = $this->getBalanceIp(self::$dns[$host][0], self::$dns[$host][1]);
        switch ($scheme){
            case self::SCHEME_TCP:
                $connection = new TcpConnection($ip, $port, $options, $this->logger);
                break;
            case self::SCHEME_SSL:
            case self::SCHEME_UNIX:
            default:
                throw new \Exception("unsupported scheme{$scheme}");
        }

        return $connection;
    }

    private function getBalanceIp(array $ips, &$counter)
    {
        $size = count($ips);
        if($size==0){
            throw new \Exception("ip list is empty");
        }
        $index = $counter%$size;
        $counter++;

        return $ips[$index];
    }

    private function buildConnectionName($scheme, $host, $port)
    {
        return "{$scheme}://{$host}:{$port}";
    }

    private function getFromIdleQueue(string $name)
    {
        if(empty($this->idleConnections[$name])){
            return false;
        }

        return array_pop($this->idleConnections[$name]);
    }

    private function addToBusyQueue(string $name, ConnectionInterface $connection)
    {
        if(empty($this->busyConnections[$name])){
            $this->busyConnections[$name] = [$connection];
        }else{
            $this->busyConnections[$name][] = $connection;
        }
    }
}