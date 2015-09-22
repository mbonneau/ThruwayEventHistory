<?php

/**
 * This script connects the the router in routerWithHistory.php.  That router is configured to provide
 * the previous 5 items that were sent on the subscription "my.topic".@global
 *
 * There are 2 clients in here, the first publishes 8 events. 5 seconds later a second
 * client subscribes to the topic and receives the last 5 that the first client published
 */

require_once __DIR__ . '/../vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$clientPublisher = new \Thruway\Peer\Client('realm1', $loop);

$clientPublisher->addTransportProvider(new \Thruway\Transport\PawlTransportProvider("ws://127.0.0.1:8080/"));

$clientPublisher->on('open', function (\Thruway\ClientSession $session) {
    $session->publish('my.topic', ["this"]);
    $session->publish('my.topic', ["is"]);
    $session->publish('my.topic', ["a"]);
    $session->publish('my.topic', ["test"]);
    $session->publish('my.topic', ["of"]);
    $session->publish('my.topic', ["the"]);
    $session->publish('my.topic', ["state"]);
    $session->publish('my.topic', ["stuff"]);

    \Thruway\Logging\Logger::info(null, "Finished publishing the items...");
});

$clientPublisher->start(false);

$loop->addTimer(5, function () use ($loop) {
    $clientSubscriber = new \Thruway\Peer\Client('realm1', $loop);
    $clientSubscriber->addTransportProvider(new \Thruway\Transport\PawlTransportProvider("ws://127.0.0.1:8080/"));

    $clientSubscriber->on('open', function (\Thruway\ClientSession $session) {
        \Thruway\Logging\Logger::info(null, "Subscribing from the second client...");

        $session->subscribe('my.topic', function ($args) {
            \Thruway\Logging\Logger::info(null, "Second client got event with args: " . json_encode($args) . "\n");
        });
    });

    $clientSubscriber->start(false);
});

$loop->run();