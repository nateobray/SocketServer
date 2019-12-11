<?php
namespace obray\eventLoops;

class StreamSelectEventLoop
{
    
    public function __construct($socket, $timeout)
    {
        $this->socket = $socket;
        $this->selectTimeout = $timeout;
    }

    public function run($callback)
    {
        $sockets = [];
        while(true){
            $changed = $sockets; $null = NULL;
            $changed[] = $this->socket;
            stream_select( $changed, $null, $null, 0, $this->selectTimeout );
            $sockets = $callback($changed);
        }        
    }
}