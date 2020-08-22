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
    
    // internal
    private $eventLoopType;
    private $socketWatcher;
    private $connections = [];
    
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
    }

    /**
     * Start
     * 
     * Starts the socket server by attempt to bind on the host and port specified.  If successfull it start the
     * stream select loop to and handle incoming and outgoing data
     */

    public function start(\obray\interfaces\SocketServerHandlerInterface $handler)
    {
        $this->handler = $handler;
        $errno = 0; $errstr = '';
        $listenstr = $this->protocol."://".$this->host.":".$this->port;
        $this->socket = @stream_socket_server($listenstr,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$this->context->get());
        if( !is_resource($this->socket) ){
			throw new \Exception("Unable to bind to ".$this->host.":".$this->port." over ".$this->protocol."\n");
        }
        print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
        
        // start event loop
        if( $this->eventLoopType === NULL && !class_exists( '\EV' || $this->eventLoopType === EV ) ) {
            // create new event loop
            $this->eventLoop = new \obray\eventLoops\EVLoop();
            // add watcher for new connections
            $this->mainWatcher = $this->eventLoop->watchStreamSocket($this->socket, function($watcher){
                $this->connectNewSockets($watcher->data);
            }, $this->socket);
            // run the event loop
            $this->eventLoop->run();

        // start stream select loop
        } else {
            $this->eventLoop = new \obray\eventLoops\StreamSelectEventLoop($this->socket);
            $this->mainWatcher = $this->eventLoop->watchStreamSocket($this->socket, function($watcher){
                $this->connectNewSockets($watcher->data);
            }, $this->socket);
            $this->eventLoop->run();            
        }
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
        // attempt to accept a new socket connection
        $connection = new \obray\SocketConnection($socket, $this->eventLoop, $this->handler, $this->context->isEncrypted());
        if($connection !== false){ 
            // start watching the connection
            $connection->run();
            // save the connection
            $this->connections[] = $connection;
            // return true on success
            return true;
        }
        // return false on failure
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
}
