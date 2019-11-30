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
        
        //print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
        // start stream select loop
        $start = 0; $end = 0; $endBytesRead = 0; $startBytesRead = 0; $kbReadPerSecond = 0;
        while(true){
            ++$this->loops;
            $end = \microtime(true);
            $endBytesRead = $this->totalBytesRead;
            $elapsed = $end - $start;
            if( ($endBytesRead - $startBytesRead) != 0 && $elapsed != 0){
                $kbReadPerSecond = (($endBytesRead - $startBytesRead) / $elapsed) / 1000;
            } else {
                $kbReadPerSecond = 0;
            }
            
            $start = \time();
            $startBytesRead = $this->totalBytesRead;
            if($this->loops % 50 == 0){
                system('clear');
                print_r("Listening on ".$this->host.":".$this->port." over ".$this->protocol."\n");
                print_r(count($this->sockets)." connection(s)\n");
                print_r("Read speed: " . number_format($kbReadPerSecond, 0, '.', ',') . " kb/s\n");
                print_r(\number_format($this->totalBytesWritten/1000, 2, '.', ',')." kb written\n");
                print_r(\number_format($this->totalBytesRead/1000, 2, '.', ',')." kb read\n");
            }
            $changed = $this->sockets; $null = NULL;
            $changed[] = $this->socket;
            stream_select( $changed, $null, $null, 0, 10000 );

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
                    $newData = fread($socket, 8192); // read in 8kb chunks.
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
        forEach($this->socketDataToWrite as $index => $data){
            $socket = $this->sockets[$index];
            $bytesWritten = fwrite($socket, $data, 8192);
            $this->totalBytesWritten += $bytesWritten;
            if($bytesWritten < 8192){
                unset($this->socketDataToWrite[$index]);
            } else {
                $this->socketDataToWrite[$index] = substr($data, $bytesWritten-1);
            }
        }
    }

    /**
     * Write
     * 
     * Very simply adds items to an array to be written to the corresponding socket
     * in the main server loop.  This keeps all writes non-blocking.
     */

    private function write($socket, string $data)
    {
        $index = array_search($socket, $this->sockets);
        $this->socketDataToWrite[$index] = $data;
    }

    /**
     * On Data
     * 
     * Called when data is received from a socket.  The socket the data is recieved on
     * is then passed in with the data read.
     */

    public function onData(string $data, $socket)
    {
        //print_r("|".$data."|\n");
        $this->write($socket, $data);
    }

}