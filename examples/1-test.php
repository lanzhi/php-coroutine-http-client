<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/10
 * Time: 下午1:35
 */

use Symfony\Component\Console\Output\ConsoleOutput;
use lanzhi\http\Client;
use Symfony\Component\Console\Logger\ConsoleLogger;

include __DIR__."/../vendor/autoload.php";

$uri = 'http://php.net/manual/en/function.socket-select.php';
//$uri = 'https://www.baidu.com/s?ie=utf-8&f=8&rsv_bp=1&rsv_idx=1&tn=baidu&wd=nginx%20%E5%BC%80%E5%90%AFgzip&oq=rmccue%252Frequests&rsv_pq=884a0e5b00028b37&rsv_t=df9e7ZUyU9ZoGPAw9s13JHJEKYKUoDI1%2FbSrQd3sPdyITfunCmi3GbMozWw&rqlang=cn&rsv_enter=1&inputT=14242277&rsv_sug3=28&rsv_sug1=24&rsv_sug7=100&sug=nginx%2520%25E5%25BC%2580%25E5%2590%25AFgzip&rsv_n=1&bs=rmccue%2Frequests';
//$uri = 'http://test.com/test.html';
//$uri = 'http://rango.swoole.com/archives/334';
//$uri = 'http://www.discuz.net/forum.php';
//$uri = 'http://100.73.0.56:8106/sa?project=default&token=2f55c43b8a7156848fe93e2663c5258fbc5197937249e382ff075b84451d5453';
//$uri = 'http://sms.test.jiedaibao.com/sms/template/innerApi/delete';
$query = [
    'one'=>1,
    'two'=>2,
    'china'=>'中国'
];
ksort($query);

//$uri = 'test.com/get.php';

$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
$client = new Client([], new ConsoleLogger($output));
$task = $client->get($uri, ['query'=>$query]);

$task->run();

$response = $task->getReturn();
if($response->getBody()){
    file_put_contents(__DIR__."/../tests/hello.html", $response->getBody()->getContents());
}else{
    var_dump($response);
}

echo $response->getStatusCode(), "\n";
