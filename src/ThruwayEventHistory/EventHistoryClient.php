<?php


namespace Voryx\Thruway\EventHistory;

use Thruway\Logging\Logger;
use Thruway\Peer\Client;

class EventHistoryClient extends Client
{
    private $uri;
    private $depth;

    private $messageHistory = [];

    /**
     * EventHistoryModule constructor.
     * @param string $realm
     * @param \React\EventLoop\LoopInterface $loop
     * @param string $uri
     * @param int $depth
     */
    public function __construct($realm, $loop, $uri, $depth = 1)
    {
        $this->uri   = $uri;
        $this->depth = $depth;

        parent::__construct($realm, $loop);
    }


    /**
     * @inheritdoc
     */
    public function onSessionStart($session, $transport)
    {
        // we subscribe to the topic so we can get the button changes
        $subscriptionPromise = $session->subscribe($this->uri, [$this, "subscriptionHandler"]);

        // register the RPC that will be providing the state information upon subscription
        $registrationPromise = $session->register($this->uri . '.state_handler', [$this, 'subscribeHistoryHandler']);

        // wait for subscription and registration promises to be fulfilled, the tell the router
        // we will be handling state
        \React\Promise\all([$subscriptionPromise, $registrationPromise])->then(function () use ($session) {
            $config = (object)[
                "uri"         => $this->uri, // This is the URI that we want provide for when there is a subscription
                "handler_uri" => $this->uri . '.state_handler', // This is the RPC that the router will call (our RPC)
                "options"     => new \stdClass() // options for the handler (prefix matching etc. if desired)
            ];

            Logger::info($this, "Calling add_state_handler for " . json_encode($config));
            $session->call('add_state_handler', [$config])->then(function () {
                Logger::info($this, "State handler for $this->uri reigstered successfully.");
            },
                function ($err) {
                    Logger::error($this, "Error: " . json_encode($err));
                });
        });
    }

    public function subscribeHistoryHandler($rpcArgs)
    {
        $uri                 = $rpcArgs[0];
        $sessionId           = $rpcArgs[1];
        $subscriptionOptions = $rpcArgs[2];

        Logger::info($this, "Someone subscribed - giving them event history.\n");

        // setup special options for state restore
        // these are needed to instruct the router to only send to the one session and to
        // send these before any events that happen in between the subscribe and before we are done getting the
        // subscriber up to date
        $publishOptions = (object)[
            "eligible"                 => [$sessionId],
            "_thruway_restoring_state" => true
        ];

        $lastPublicationId = null;
        foreach ($this->messageHistory as $record) {
            $lastPublicationId = $record["publicationId"];
            $this->getSession()->publish($this->uri, $record["args"], $record["argsKw"], $publishOptions);
        }

        return $lastPublicationId;
    }

    function subscriptionHandler($args, $argsKw, $details, $publicationId)
    {
        $this->messageHistory[] = ["args" => $args, "argsKw" => $argsKw, "publicationId" => $publicationId];
        while (count($this->messageHistory) > $this->depth) {
            array_shift($this->messageHistory);
        }
    }
}