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

    // socket management
    private $sockets = [];
    private $socketDataReceived = [];
    private $socketDataToWrite = [];

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
        while(true){
            ++$this->loops;
            $this->displayServerStatus();
            
            $changed = $this->sockets; $null = NULL;
            $changed[] = $this->socket;
            stream_select( $changed, $null, $null, 0, 5000 );

            // connect new socket connections
            $this->connectNewSockets($changed);
            
            // read socket data (see which sockets we need to read from)
            $this->readSocketData($changed);

            // write socket data
            $this->writeSocketData();
        }        
    }

    /**
     * Connect New Sockets
     * 
     * Identifies new connections coming through on the established network bindind and
     * creates a new connection read to send and receive data.  It also sets the stream
     * to non-blocking so we can handle many requests coming in an the same time.
     */

    private function connectNewSockets(array $changed)
    {
        if( in_array($this->socket,$changed) ){
            $new_socket = @stream_socket_accept($this->socket,1);
            if( !$new_socket ){
                return FALSE;
            }
            //print_r("Connected\n");
            $this->sockets[] = $new_socket;
            stream_set_blocking($new_socket, false);
            return $new_socket;
        }
    }

    /**
     * Read Socket Data
     * 
     * Looks for data coming in on existing socket connections and attempts to read the
     * data off the stream in 8kb increments.  When it finishes reading it pass the data
     * to onData().
     */

    private function readSocketData(array $changed)
    {
        forEach($this->sockets as $i => $socket){
            $index = array_search($socket, $changed);
            if($index !== false){
                if( feof($socket) ){
                    //print_r("disconnected\n");
                    unset($this->sockets[$index]);
                    unset($this->socketDataReceived[$index]);
                } else {
                    $newData = fread($socket, 1024); // read in 8kb chunks.
                    $this->totalBytesRead += strlen($newData);
                    $this->onData($newData, $socket);
                }
            }
        }
    }

    /**
     * Write Socket Data
     * 
     * Looks for data waiting to be written and writes it out to the socket in 8kb
     * increments.
     */

    private function writeSocketData()
    {
        forEach($this->socketDataToWrite as $index => $array){
            $socket = $this->sockets[$index];
            forEach($array as $i => $data){
                $bytesWritten = fwrite($socket, $data, 1024);
                $this->totalBytesWritten += $bytesWritten;
                if($bytesWritten < 1024){
                    unset($this->socketDataToWrite[$index][$i]);
                    if(empty($this->socketDataToWrite[$index])) unset($this->socketDataToWrite[$index]);
                } else {
                    $this->socketDataToWrite[$index][$i] = substr($data, $bytesWritten-1);
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

            system('clear');
            print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
            print_r(count($this->sockets)." connection(s)\n");
            print_r("Read speed: " . number_format($this->kbReadPerSecond, 0, '.', ',') . " kb/s\n");
            print_r("Write speed: " . number_format($this->kbWrittenPerSecond, 0, '.', ',') . " kb/s\n");
            print_r(\number_format($this->totalBytesWritten/1000, 2, '.', ',')." kb written\n");
            print_r(\number_format($this->totalBytesRead/1000, 2, '.', ',')." kb read\n");
        }
    }

    /**
     * Write
     * 
     * Very simply adds items to an array to be written to the corresponding socket
     * in the main server loop.  This keeps all writes non-blocking.
     */

    public function write($socket, string $data)
    {
        $index = array_search($socket, $this->sockets);
        if(empty($this->socketDataToWrite)) $this->socketDataToWrite = [];
        $this->socketDataToWrite[$index][] = $data;
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

}