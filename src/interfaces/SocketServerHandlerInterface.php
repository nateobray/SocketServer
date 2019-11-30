<?php

namespace obray\interfaces;

interface SocketServerHandlerInterface 
{
    public function onData(string $data, $socket, \obray\SocketServer $server): void;
}