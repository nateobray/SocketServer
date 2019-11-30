<?php
namespace obray\handlers;

class TestServer implements \obray\interfaces\SocketServerHandlerInterface
{
    public function onData(string $data, $socket, \obray\SocketServer $server): void
    {
        
        $server->write($socket, $data);
    }
}