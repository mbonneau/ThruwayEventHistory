<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$router = new \Thruway\Peer\Router($loop);

$router->registerModules([
    new \Thruway\Transport\RatchetTransportProvider("127.0.0.1", 8080),
    new \Thruway\Subscription\StateHandlerRegistry('realm1', $loop)
]);

// note that this must be added after the StateHandlerRegistry is added
// this client does not have to be run inside the router - it can be run in its
// own process
$router->addInternalClient(new \Voryx\Thruway\EventHistory\EventHistoryClient('realm1', $loop, 'my.topic', 5));

$router->start();