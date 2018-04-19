<?php

use lanzhi\http\Client;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

include __DIR__."/../vendor/autoload.php";

$client = new Client();

$uri = 'http://php.net/manual/en/function.socket-select.php';
$scheduler = \lanzhi\coroutine\Scheduler::getInstance();
$connector = \lanzhi\socket\Connector::getInstance();

$startTime = microtime(true);
for($i=0; $i<1000; $i++){
    $request = $client->get($uri);

    $routine = new \lanzhi\coroutine\FlexibleRoutine();
    $routine->add($request);

    $generator = function () use($request, $i){
        yield;
        $response = $request->getReturn();
        echo "{$i} # response code:{$response->getStatusCode()}; body size:{$response->getBody()->getSize()}\n";
    };
    $routine->add(\lanzhi\coroutine\Scheduler::buildRoutineUnit($generator()));

    $scheduler->register($routine);
}

$scheduler->run();
echo "time usage:", microtime(true)-$startTime, "\n";
