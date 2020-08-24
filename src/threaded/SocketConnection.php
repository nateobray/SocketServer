<?php

namespace obray\threaded;

class SocketConnection extends \Volatile implements \obray\interfaces\SocketConnectionInterface
{
    private $socket;
    private $eventLoop;
    private $readChunkSize = 8129;
    private $totalBytesRead = 0;
    private $totalBytesWritten = 0;
    private $socketDataToWrite = [];
    private $maxWriteRetries = 100;
    private $handler = null;
    private $shouldDisconnect = false;
    private $isConnected = false;

    //private $writeWatcher;
    //private $readWatcher;
    
    public function __construct($mainSocket, $eventLoop, \obray\interfaces\SocketServerHandlerInterface $handler, bool $shouldSecure=false)
    {
        // save handler
        $this->handler = $handler;
        // call handler on connect
        $this->handler->onConnect($this);
        // attempting to connect new socket
        $socket = @stream_socket_accept($mainSocket,1);
        // handle connection failure
        if(!$socket){
            $this->handler->onConnectFailed($this);
            return;
        }
        // establish secure connection if required
        if($shouldSecure){
            stream_set_blocking($socket, true);
            if(!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_0_SERVER|STREAM_CRYPTO_METHOD_TLSv1_1_SERVER|STREAM_CRYPTO_METHOD_TLSv1_2_SERVER)){
                $this->handler->onConnectFailed($this);
                return;
            }
        }
        // make sure socket is in non-blocking mode
        stream_set_blocking($socket, false);
        // set connection properties
        $this->socket = $socket;
        $this->eventLoop = $eventLoop;
        $this->isConnected = true;
        $this->loop = new \EvLoop();
        //call on connected
        $this->handler->onConnected($this);
    }

    public function run()
    {
        
        $newLoop = new \EvLoop();
        // watch for changes on our socket and fire event accordingly
        $readWatcher = $newLoop->io($this->socket, \Ev::READ, function($w){
            $this->readSocketData($w);
        });
        
        // periodically check to see if data is available to write
        $writeWatcher = $newLoop->periodic(0, 0.0001, null, function($w){
            //print_r("write!\n");
            $this->writeSocketData();
        });
        
        // run the new event loop and start checking for events
        $newLoop->run();
        
    }

    /**
     * Read Socket Data
     * 
     * Looks for data coming in on existing socket connections and attempts to read the
     * data off the stream in 8kb increments.  When it finishes reading it pass the data
     * to onData().
     */

    private function readSocketData($w): void
    {
        // check if connect still open
        if( feof($this->socket) ){
            
            $this->disconnect();
        } else {
            $shouldRead = true; $data = '';
            while($shouldRead){
                // read from socket
                $newData = @fread($this->socket, $this->readChunkSize);
                // handle error condition
                if($newData === false){ 
                    if($this->handler !== null){
                        $this->handler->onReadFailed($this);
                    }
                    continue;
                }
                $data .= $newData;
                $this->totalBytesRead += mb_strlen($newData, '8bit');
                if(stream_get_meta_data($this->socket)['unread_bytes'] > 0) continue;
                $shouldRead = false;
            }
            if($this->handler !== null){
                
                $this->handler->onData($data, $this);
            }
        }
    }

    /**
     * Write Socket Data
     * 
     * Looks for data waiting to be written and writes it out to the socket in 8kb
     * increments.
     */

    private function writeSocketData(): void
    {
        // if no data vailable to write return
        if(empty($this->socketDataToWrite)) return;
        // loop through data to write and write it the socket connection
        forEach($this->socketDataToWrite as $i => $data){
            $retries = 0;
            while(!empty($this->socketDataToWrite[$i])){
                $bytesWritten = @fwrite($this->socket, $this->socketDataToWrite[$i]);
                if($bytesWritten === false || $retries > $this->maxWriteRetries ) {
                    if($this->handler !== null){
                        $this->handler->onWriteFailed($this->socketDataToWrite[$i], $this);
                    }
                    break;
                }
                $this->totalBytesWritten += $bytesWritten;
                if($bytesWritten < mb_strlen($this->socketDataToWrite[$i])){
                    ++$retries;
                    $this->socketDataToWrite[$i] = mb_strcut($this->socketDataToWrite[$i], $bytesWritten);
                } else {
                    unset($this->socketDataToWrite[$i]);
                }
            }
        }
        if($this->shouldDisconnect) $this->disconnect();
    }

    /**
     * Q Write
     * 
     * Very simply adds items to an array to be written to the corresponding socket
     * in the main server loop.  This keeps all writes non-blocking.
     */

    public function qWrite(string $data)
    {
        $this->socketDataToWrite[] = $data;
    }

    /**
     * Q Disconnect
     * 
     * Determines if this connection should terminate after writing all the data
     * it has available to write.
     */

    public function qDisconnect()
    {
        $this->shouldDisconnect = true;
    }

    /**
     * Disconnect
     * 
     * Shuts down the socket connection and prevents and addtional reads and writes
     * to that socket.  Also removes it from the list of sockets and socket data
     */

    public function disconnect()
    {
        //\Ev::stop();
        print_r("Disconnected.\n");
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        $this->writeWatcher = null;
        $this->readWatcher = null;
        $this->isConnected = false;
        exit();

    }

    /**
     * isConnected
     * 
     * Will return the state of the underlying socket connection as tracked by this
     * class.
     */

    public function isConnected()
    {
        return $this->isConnected;
    }
}
