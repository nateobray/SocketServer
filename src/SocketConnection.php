<?php

namespace obray;

class SocketConnection implements \obray\interfaces\SocketConnectionInterface
{
    const READ_UNTIL_EMPTY = 0;
    const READ_UNTIL_CONNECTION_CLOSED = 1;
    const READ_UNTIL_LINE_ENDING = 2;
    const READ_UNTIL_LENGTH = 3;

    private $socket;
    private $eventLoop;
    private $readChunkSize = 8192;
    private $totalBytesRead = 0;
    private $totalBytesWritten = 0;
    private $socketDataToWrite = [];
    private $maxWriteRetries = 1000;
    private $handler = null;
    private $shouldDisconnect = false;
    private $isConnected = false;
    private $retries = 0;

    private $readMethod = 0;
    private $readEOL = "\n";
    private $initialReadLength = 8192;

    private $writeWatcher;
    private $readWatcher;

    public function __construct($mainSocket, $eventLoop, \obray\interfaces\SocketServerHandlerInterface $handler, bool $shouldSecure=false, bool $isServer=true, int $readMethod=self::READ_UNTIL_EMPTY)
    {
        // save handler
        $this->handler = $handler;
        // call handler on connect
        $this->handler->onConnect($this);
        // attempting to connect new socket
        if($isServer){
            $socket = @stream_socket_accept($mainSocket,1);
        } else {
            $socket = $mainSocket;
        }
        
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
        $this->readMethod = $readMethod;
        //call on connected
        $this->handler->onConnected($this);
    }

    public function setInitialReadSize(int $initialReadSize): void
    {
        $this->initialReadSize = $initialReadSize;
    }

    public function setEOL(string $eol): void
    {
        $this->readEOL = $eol;
    }

    public function setReadMethod(int $readMethod): void
    {
        if(!in_array($readMethod, [0, 1, 2, 3])) throw new \Exception("Invalid read method specified.");
        $this->readMethod = $readMethod;
    }

    public function run()
    {
        // watch for changse on our socket and fire event accordingly
        $this->writeWatcher = $this->eventLoop->watchStreamSocket($this->socket, function($w){
            $this->readSocketData();
        }, null);
        // periodically check to see if data is available to write
        $this->readWatcher = $this->eventLoop->watchTimer(0, 0.0001, function($w){
            $this->writeSocketData();
        }, null);
    }

    /**
     * Read Socket Data
     * 
     * Looks for data coming in on existing socket connections and attempts to read the
     * data off the stream in 8kb increments.  When it finishes reading it pass the data
     * to onData().
     */

    private function readSocketData(): void
    {
        // check if connect still open
        if( feof($this->socket) ){
            $this->disconnect();
        } else {
            $shouldRead = true; $data = ''; $readLength = $this->readChunkSize; $lengthNeed = false; $readRetries = 0;
            if($this->readMethod === self::READ_UNTIL_LENGTH){
                $readLength = $this->initialReadLength;
            }
            while($shouldRead){

                // read from socket until line delimeter
                if($this->readMethod === self::READ_UNTIL_LINE_ENDING){
                    $newData = stream_get_line($this->socket, $readLength, $this->readEOL);
                // read form socket length of readLength
                } else {
                    $newData = @fread($this->socket, $readLength);
                }

                if(feof($this->socket)) {
                    $data .= $newData;
                    $this->handler->onData($data, mb_strlen($data, '8bit'), $this);
                    return;
                }
                
                // handle error condition
                if($newData === false){ 
                    if($readRetries > 10 && $this->handler !== null){
                        $this->handler->onReadFailed($this);
                    }
                    continue;
                }

                if(in_array($this->readMethod, [self::READ_UNTIL_LINE_ENDING]) && $this->handler !== null) {
                    $lengthNeeded = $this->handler->onData($newData, $readLength, $this);
                    $data = '';
                    if( $lengthNeeded === false) return;
                } else {
                    $data .= $newData;
                }

                $this->totalBytesRead += mb_strlen($newData, '8bit');
                if(feof($this->socket) && $this->readMethod === self::READ_UNTIL_CONNECTION_CLOSED) $shouldRead = false;
                if(empty($newData) && $this->readMethod === self::READ_UNTIL_EMPTY) $shouldRead = false;
                if(mb_strlen($data, '8bit') === $lengthNeeded){
                    $shouldRead = false;
                }
            }
            if($this->handler !== null && in_array($this->readMethod, [self::READ_UNTIL_CONNECTION_CLOSED, self::READ_UNTIL_EMPTY, self::READ_UNTIL_LENGTH])){
                $this->handler->onData($data, mb_strlen($data, '8bit'), $this);
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
            $this->retries = 0;
            while(!empty($this->socketDataToWrite[$i])){
                $bytesWritten = @fwrite($this->socket, $this->socketDataToWrite[$i], $this->readChunkSize);
                if($bytesWritten === false || $this->retries > $this->maxWriteRetries ) {
                    $this->handler->onWriteFailed($this->socketDataToWrite[$i], $this);
                    return;
                }
                $this->totalBytesWritten += $bytesWritten;
                if($bytesWritten < strlen($this->socketDataToWrite[$i])){
                    ++$this->retries;
                    $this->socketDataToWrite[$i] = substr($this->socketDataToWrite[$i], $bytesWritten);
                    return;
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
        fclose($this->socket);
        $this->writeWatcher = null;
        $this->readWatcher = null;
        $this->isConnected = false;

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

    /**
     * Get Socket
     * 
     * Gets the underlying socket connection and returns it
     */

    public function getSocket()
    {
        return $this->socket;
    }
}
