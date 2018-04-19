<?php

use lanzhi\http\Client;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

include __DIR__."/../vendor/autoload.php";

$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_VERY_VERBOSE);
$client = new Client([], new ConsoleLogger($output));


$data = [
    'name' => 'lanzhi',
    'sex'  => 'male',
    'age'  => 'unknown',
    'tag'  => uniqid()
];
$uri = 'test.com/post.php';

// multipart/form-data 请求
echo "HTTP POST; multipart/form-data 请求:\n";
$request = $client->post($uri, ['multipart'=>buildMultipartData($data)]);
$request->run();
$response = $request->getReturn();
if($response->getBody()){
    echo "response body size:", $response->getBody()->getSize(), "\n";
    echo "body:  ", $response->getBody()->getContents(), "\n";
}else{
    var_dump($response);
}

echo "response status:", $response->getStatusCode(), "\n";
echo "response phrase:", $response->getReasonPhrase(), "\n\n";


// application/x-www-form-urlencoded 请求
echo "\nHTTP POST; application/x-www-form-urlencoded 请求:\n";
$request = $client->post($uri, ['form_params'=>$data]);
$request->run();
$response = $request->getReturn();
if($response->getBody()){
    echo "response body size:", $response->getBody()->getSize(), "\n";
    echo "body:  ", $response->getBody()->getContents(), "\n";
}else{
    var_dump($response);
}

echo "response status:", $response->getStatusCode(), "\n";
echo "response phrase:", $response->getReasonPhrase(), "\n\n";



function buildMultipartData(array $data)
{
    $list = [];
    foreach ($data as $key=>$value){
        $list[] = [
            'name'     => $key,
            'contents' => $value
        ];
    }
    return $list;
}