<?php

use lanzhi\http\Client;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

include __DIR__."/../vendor/autoload.php";

$uri = 'http://php.net/manual/en/function.socket-accept.php';

$client = new \GuzzleHttp\Client();

$startTime = microtime(true);
for($i=0; $i<500; $i++){
    $response = $client->get($uri);
    echo "{$i} # response code:{$response->getStatusCode()}; body size:{$response->getBody()->getSize()}\n";
}

echo "time usage:", microtime(true)-$startTime, "\n";
