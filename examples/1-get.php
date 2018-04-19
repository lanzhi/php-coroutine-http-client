<?php

use lanzhi\http\Client;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

include __DIR__."/../vendor/autoload.php";


$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
$client = new Client([], new ConsoleLogger($output));

//无参数请求
$uri = 'http://php.net/manual/en/function.socket-select.php';
$request = $client->get($uri);
echo "HTTP GET; 无参数请求\n";
echo get_class($request), "\n";
echo spl_object_hash($request), "\n";
$request->run();

if($request->hasException()){
    var_dump($request->getException()->getMessage());
    print_r($request->getException()->getTrace());
}



$response = $request->getReturn();




if($response->getBody()){
    echo "response body size:", $response->getBody()->getSize(), "\n";
}else{
    var_dump($response);
}

echo "response status:", $response->getStatusCode(), "\n";
echo "response phrase:", $response->getReasonPhrase(), "\n";

/**
 * file: get.php
 * ```php
 *
 * $query = $_GET;
 * ksort($query);
 * $json = json_encode($query, JSON_UNESCAPED_UNICODE);
 * echo $json;
 *
 * ```
 */
echo "HTTP GET; 有参请求\n";
$uri = "test.com/get.php";
$query = [
    'name' => 'lanzhi',
    'sex'  => 'male',
    'age'  => 'unknown',
    'tag'  => uniqid()
];

$request = $client->get($uri, ['query'=>$query]);
$request->run();
$response = $request->getReturn();
if($response->getBody()){
    echo "response body size:", $response->getBody()->getSize(), "\n";
    ksort($query);
    echo "query: ", json_encode($query, JSON_UNESCAPED_UNICODE), "\n";
    echo "body:  ", $response->getBody()->getContents(), "\n";
}else{
    var_dump($response);
}

echo "response status:", $response->getStatusCode(), "\n";
echo "response phrase:", $response->getReasonPhrase(), "\n\n";

