<?php

namespace obray;

class SocketServer 
{
    // connection details
    private $protocol;
    private $host;
    private $port;
    private $context;
    private $socket;
    private $socketWatcher;

    // socket management
    private $sockets = [];
    private $socketWatchers = [];
    private $socketDataReceived = [];
    private $socketDataToWrite = [];
    private $disconnectQueue = [];

    // internal
    private $loops = 0;
    private $totalBytesWritten = 0;
    private $totalBytesRead = 0;
    private $displayServerStatus = true;
    private $start = 0;
    private $end = 0;
    private $startBytesRead = 0;
    private $endBytesRead = 0;
    private $kbReadPerSecond = 0;
    private $kbWrittenPerSecond = 0;
    private $selectTimeout = 500;
    private $readChunkSize = 8129;
    private $writeChunkSize = 8129;
    private $maxWriteRetries = 10;
    
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

    public function start()
    {
        $errno = 0; $errstr = '';
        $listenstr = $this->protocol."://".$this->host.":".$this->port;
        $this->socket = @stream_socket_server($listenstr,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,$this->context->get());
        if( !is_resource($this->socket) ){
			throw new \Exception("Unable to bind to ".$this->host.":".$this->port." over ".$this->protocol."\n");
        }
        
        print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
        // start stream select loop
        $start = 0; $end = 0; $endBytesRead = 0; $startBytesRead = 0; $kbReadPerSecond = 0;

        if( class_exists( '\EV' ) ) {
            $this->socketWatcher = new \EvIo($this->socket, \Ev::READ, function($watcher, $w2){
                $this->connectNewSockets($watcher->data);
            }, $this->socket); 
            
            \EV::run();   
        } else {
            print_r("select default event loop (uses stream_select)");
            $evLoop = new \obray\eventLoops\StreamSelectEventLoop($this->socket, $this->selectTimeout);
            $evLoop->run([$this, 'loop']);            
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
        $this->onConnect($socket);
        $new_socket = @stream_socket_accept($socket,1);
        if( !$new_socket ){
            print_r("Failed to connect\n");
            return FALSE;
        }
        $this->sockets[] = $new_socket;
        stream_set_blocking($new_socket, false);
        $this->onConnected($new_socket);
        
        $this->socketWatchers[] = new \EvIo($new_socket, \Ev::WRITE|\Ev::READ, function($w){
            //echo "ready child socket\n";
            ++$this->loops;
            $this->writeSocketData($w->data);
            $this->readSocketData($w->data);
            $this->displayServerStatus();
        }, $new_socket);    
        return $new_socket;
    }

    /**
     * Disconnect Sockets
     * 
     * Goes through the list of queued disconnects and calls disconnect on all of them.
     * This will trigger onDisconnect and onDisconnected.
     */

    private function disconnectSockets()
    {
        forEach($this->disconnectQueue as $socket) {
            $this->disconnect($socket
        );
        }
    }

    /**
     * Disconnect
     * 
     * Shuts down the socket connection and prevents and addtional reads and writes
     * to that socket.  Also removes it from the list of sockets and socket data
     */

    private function disconnect($socket)
    {
        $index = array_search($socket, $this->sockets);
        $this->onDisconnect($socket);
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        unset($this->sockets[$index]);
        unset($this->socketDataReceived[$index]);
        unset($this->socketWatchers[$index]);
        unset($this->socketDataToWrite[$index]);
        $this->onDisconnected($socket);
    }

    /**
     * Read Socket Data
     * 
     * Looks for data coming in on existing socket connections and attempts to read the
     * data off the stream in 8kb increments.  When it finishes reading it pass the data
     * to onData().
     */

    private function readSocketData($socket)
    {
        if( feof($socket) ){
            $this->disconnect($socket);
        } else {
            $shouldRead = true; $data = '';
            while($shouldRead){
                // read from socket
                $newData = @fread($socket, $this->readChunkSize);
                // handle error condition
                if($newData === false){ 
                    $this->disconnect($socket);
                    continue;
                }
                $data .= $newData;
                $this->totalBytesRead += mb_strlen($newData, '8bit');
                if(stream_get_meta_data($socket)['unread_bytes'] > 0) continue;
                $shouldRead = false;
            }
            $this->onData($data, $socket);
        }
    }

    /**
     * Write Socket Data
     * 
     * Looks for data waiting to be written and writes it out to the socket in 8kb
     * increments.
     */

    private function writeSocketData($socket)
    {
        $index = array_search($socket, $this->sockets);   
        if(empty($this->socketDataToWrite[$index])) return;

        forEach($this->socketDataToWrite[$index] as $i => $data){
            $retries = 0;
            while(!empty($this->socketDataToWrite[$index][$i])){
                $bytesWritten = @fwrite($socket, $this->socketDataToWrite[$index][$i]);
                if($bytesWritten === false || $retries > $this->maxWriteRetries ) {
                    $this->disconnect($socket);
                    break;
                }
                $this->totalBytesWritten += $bytesWritten;
                if($bytesWritten < mb_strlen($this->socketDataToWrite[$index][$i])){
                    ++$retries;
                    $this->socketDataToWrite[$index][$i] = mb_strcut($this->socketDataToWrite[$index][$i], $bytesWritten);
                } else {
                    unset($this->socketDataToWrite[$index][$i]);
                    if(empty($this->socketDataToWrite[$index])) unset($this->socketDataToWrite[$index]);
                }
            }
        }
    }

    /**
     * Display Server Status
     * 
     * If enabled this will show the server
     */

    private function displayServerStatus()
    {
        if($this->displayServerStatus && $this->loops % 100 == 0){
            $this->end = \microtime(true);
            $this->endBytesRead = $this->totalBytesRead;
            $this->endBytesWritten = $this->totalBytesWritten;
            $elapsed = $this->end - $this->start;
            if( ($this->endBytesRead - $this->startBytesRead) != 0 && $elapsed != 0){
                $this->kbReadPerSecond = (($this->endBytesRead - $this->startBytesRead) / $elapsed) / 1000;
            } else {
                $this->kbReadPerSecond = 0;
            }
            if( ($this->endBytesWritten - $this->startBytesWritten) != 0 && $elapsed != 0){
                $this->kbWrittenPerSecond = (($this->endBytesWritten - $this->startBytesWritten) / $elapsed) / 1000;
            } else {
                $this->kbWrittenPerSecond = 0;
            }
            $this->start = \time();
            $this->startBytesRead = $this->totalBytesRead;
            $this->startBytesWritten = $this->totalBytesWritten;
            $loopsPerSecond = 100 / $elapsed;

            system('clear');
            print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
            print_r(count($this->sockets)." connection(s) - : ".$loopsPerSecond." loops/s\n");
            print_r("Read speed: " . number_format($this->kbReadPerSecond, 0, '.', ',') . " kb/s\n");
            print_r("Write speed: " . number_format($this->kbWrittenPerSecond, 0, '.', ',') . " kb/s\n");
            print_r(\number_format($this->totalBytesWritten/1000, 2, '.', ',')." kb written\n");
            print_r(\number_format($this->totalBytesRead/1000, 2, '.', ',')." kb read\n");
        }
    }

    /**
     * Q Write
     * 
     * Very simply adds items to an array to be written to the corresponding socket
     * in the main server loop.  This keeps all writes non-blocking.
     */

    public function qWrite($socket, string $data)
    {
        $index = array_search($socket, $this->sockets);
        if(empty($this->socketDataToWrite)) $this->socketDataToWrite = [];
        $this->socketDataToWrite[$index][] = $data;
    }

    /**
     * Q Disconnect
     * 
     * This queues a disconnect.  This is useful when you want everything that is
     * queued up to write to finish before disconnecting the client.
     */

    public function qDisconnect($socket)
    {
        $this->disconnectQueue[] = $socket;
    }

    /**
     * On Data
     * 
     * Called when data is received from a socket.  The socket the data is recieved on
     * is then passed in with the data read.
     */

    private function onData(string $data, $socket)
    {
        if($this->handler !== NULL){
            $this->handler->onData($data, $socket, $this);
        }
    }

    /**
     * On Connected
     * 
     * This checks to for a handler and calls the handlers onConnect function.  This happens
     * before the new connection is established and the socket passed is the main server
     * connection.
     */

    private function onConnect($socket)
    {
        if($this->handler !== NULL){
            $this->handler->onConnect($socket, $this);
        }
    }

    /**
     * On Connected
     * 
     * This checks to for a handler and calls the handlers onConnected function.  This happens
     * after the connect is established the the socket passed is the new socket connection.
     */

    private function onConnected($socket)
    {
        if($this->handler !== NULL){
            $this->handler->onConnected($socket, $this);
        }
    }

    /**
     * On Disconnect
     * 
     * This checks to for a handler and calls the handlers disconnect function. This allows
     * the handler to manage it's own set of active connections and remove them when a disconnect
     * happens.  This is called before the socket is shutdown.
     */

    private function onDisconnect($socket)
    {
        if($this->handler !== NULL){
            $this->handler->onDisconnect($socket, $this);
        }
    }

    /**
     * On Disconnected
     * 
     * This checks to for a handler and calls the handlers disconnected function. This allows
     * the handler to manage it's own set of active connections and remove them when a disconnected
     * happens.  This is called after the socket is shutdown.
     */

    private function onDisconnected($socket)
    {
        if($this->handler !== NULL){
            $this->handler->onDisconnected($socket, $this);
        }
    }

    /**
     * Register Handler
     * 
     * This allows you to use the socket server to create your own type of server (echo server,
     * web socket server, etc...)
     */

    public function registerHandler(\obray\interfaces\SocketServerHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Get Sockets
     * 
     * Returns a list of active sockets.  Can be used by a handler distribute messages.
     */

    public function getSockets(): array
    {
        return $this->sockets;
    }

    /**
     * Show Server Status
     * 
     * By default this is set to true, but if you don't wan the server status being
     * written to the screen you can set this to false (faster)
     */

    public function showServerStatus(bool $displayStatus): void
    {
        $this->displayServerStatus = $displayStatus;
    }

    /**
     * Set Select Timeout
     * 
     * This sets the select timeout.  A smaller number make se the server process requests in shorter
     * intervals, but also comsumes more CPU.  It's not recommended to set this to 0.
     */

     public function setSelectTImeout(int $microseconds = 5000)
     {
        $this->selectTimeout = $microseconds;
     }

    /**
     * Set Read Chunk Size
     *  
     * Set the read chunk size, the maximium size it will read off the network before read returns
     * and allows andother loop to process.
     */

    public function setReadChunkSize(int $chunkSize = 1024)
    {
        $this->readChunkSize = $chunkSize;
    }

    /**
     * Set Write Chunk Size
     * 
     * Sets the write chunk size, the maximum bytes it will write to the network before returning and
     * allowing another loop to process.
     */

    public function setWriteChunkSize(int $chunkSize = 1024){
        $this->writeChunkSize = $chunkSize;
    }

}