<?php

namespace obray;

class SocketServer
{
    const SELECT = 0;
    const EV = 1;

    // connection details
    private $protocol;
    private $host;
    private $port;
    private $context;
    private $socket;
    private $errorNo;
    private $errorMessage;

    // internal
    private $eventLoopType;
    private $eventLoop;
    private $mainWatcher;
    private $disconnectWatcher;
    private $connections = [];
    private $numFailedConnections = 0;
    private $showServerStatus = true;
    private $logger;

    // store handler
    private $handler = NULL;

    /**
     * Constructor
     * 
     * Takes the necessary data to start a connection and stores it on the sever object to be used when running
     * start.
     */

    public function __construct(string $protocol='tcp', string $host='localhost', int $port=8080, \obray\StreamContext $context=NULL)
    {
        $this->protocol = $protocol;
        $this->host = $host;
        $this->port = $port;
        $this->context = $context;
        if($this->context == NULL){
            $this->context = new \obray\StreamContext();
        }

        set_error_handler([$this, 'errorHandler'], E_WARNING | E_NOTICE | E_PARSE);
    }

    /**
     * Start
     * 
     * Starts the socket server by attempt to bind on the host and port specified.  If successfull it start the
     * stream select loop to and handle incoming and outgoing data
     */

    public function start(\obray\interfaces\SocketServerHandlerInterface $handler = null)
    {
        if($handler !== null){
            $this->handler = $handler;
        }
        if($this->handler === null){
            throw new \Exception("Socket server handler has not been registered.");
        }
        // start the server
        $this->serve();
        
        if($this->eventLoopType === self::EV && class_exists('\EV')) {
            $this->eventLoop = new \obray\eventLoops\EVLoop();
        } else {
            $this->eventLoop = new \obray\eventLoops\StreamSelectEventLoop($this->socket);
        }

        // call on start
        $this->handler->onStart($this);

        // start watching connections
        $this->watch();
    }

    public function registerHandler(\obray\interfaces\SocketServerHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function showServerStatus(bool $showServerStatus): void
    {
        $this->showServerStatus = $showServerStatus;
    }

    public function setLogger(callable $logger = null): void
    {
        $this->logger = $logger;
    }

    public function watchTimer(float $delay, float $interval, callable $callback, $data = null)
    {
        if($this->eventLoop === null){
            throw new \Exception("Cannot register timer before the event loop has been created.");
        }
        return $this->eventLoop->watchTimer($delay, $interval, $callback, $data);
    }

    /**
     * Serve
     * 
     * Simply binds a socket to a host and port.
     */

    private function serve()
    {
        $listenstr = $this->protocol."://".$this->host.":".$this->port;
        if($this->showServerStatus){
            $this->log("Connecting: " . $listenstr);
        }
        $this->socket = stream_socket_server($listenstr, $this->errorNo,$this->errorMessage,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$this->context->get());
        if( !is_resource($this->socket) ){
			throw new \Exception("Unable to bind to ".$this->host.":".$this->port." over ".$this->protocol.": " . $this->errorMessage . "\n");
        }
        if($this->showServerStatus){
            $this->log("Listening on ".$this->host.":".$this->port." over ".$this->protocol);
        }
        return true;
    }

    /**
     * Watch
     * 
     * Starts watch for network activity on main socket and establishes new connections
     * when it encounters some.
     */

    private function watch()
    {
        // add watcher for new connectionszz
        $this->mainWatcher = $this->eventLoop->watchStreamSocket($this->socket, function($watcher){
            $this->connectNewSockets($watcher->data);
        }, $this->socket);
        // add watcher for cleaning up disconnected connections from the main connection list
        $this->disconnectWatcher = $this->eventLoop->watchTimer(0, 10, function($watcher){
            forEach($this->connections as $index => $connection){
                if(!$this->connections[$index]->isConnected()) unset($this->connections[$index]);
            }
            if($this->showServerStatus){
                $this->log("Total connections: " . count($this->connections));
            }
        }, $this->socket);
        // run the event loop
        $this->eventLoop->run();
    }

    /**
     * Connect New Sockets
     * 
     * Identifies new connections coming through on the established network bindind and
     * creates a new connection read to send and receive data.  It also sets the stream
     * to non-blocking so we can handle many requests coming in an the same time.
     */

    private function connectNewSockets($socket)
    {
        try {
            $connection = new \obray\SocketConnection($socket, $this->eventLoop, $this->handler, $this->context->isEncrypted());
        } catch (\obray\exceptions\SocketFailureException $e) {
            if($this->mainWatcher !== null){
                $this->mainWatcher->stop();
            }
            if($this->eventLoop !== null){
                $this->eventLoop->stop();
            }
            throw $e;
        }

        if($connection->isConnected()){
            $this->numFailedConnections = 0;
            $connection->run();
            $this->connections[] = $connection;
            return true;
        }

        ++$this->numFailedConnections;
        if($this->numFailedConnections > 10000){
            if($this->mainWatcher !== null){
                $this->mainWatcher->stop();
            }
            if($this->eventLoop !== null){
                $this->eventLoop->stop();
            }
            throw new \obray\exceptions\SocketFailureException("Too many failed socket connections.");
        }
        return false;
    }

    /**
     * Get Sockets
     * 
     * Returns a list of active sockets.  Can be used by a handler distribute messages.
     */

    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Set Select Timeout
     * 
     * This sets the select timeout.  A smaller number make se the server process requests in shorter
     * intervals, but also comsumes more CPU.  It's not recommended to set this to 0.
     */

    public function setEventLoopType(int $eventLoopType)
    {
        $this->eventLoopType = $eventLoopType;
    }

    /**
     * Custom Error Handler
     * 
     * There seems to be a case when we loose our main socket connection that we need to terminate the
     * server
     */

    public function errorHandler(int $errno ,string $errstr, string $errfile, int $errline, array $errcontext = [])
    {
        switch($errno){
            // and warnings
            case E_WARNING:
                $this->log("(".$errno.") " . $errstr);
                if($errstr == 'stream_socket_accept(): accept failed: Invalid argument'){
                    $this->log("Error: (".$errno.") " . $errstr);
                    throw new \obray\exceptions\SocketFailureException();
                } else if (strpos($errstr, "stream_socket_accept(): accept failed: Too many open files in") !== false){
                    $this->log("Error: (".$errno.") " . $errstr);
                    throw new \obray\exceptions\SocketFailureException();
                }
            break;
            // print everything else to screen
            default:
                $this->log("(".$errno.") " . $errstr);
            break;
        }
        
    }

    private function log(string $message): void
    {
        if($this->logger !== null){
            ($this->logger)($message);
            return;
        }
        if($this->showServerStatus){
            print_r($message . "\n");
        }
    }
}
