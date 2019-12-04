<?php
namespace obray\handlers;

class EchoServer implements \obray\interfaces\SocketServerHandlerInterface
{
    public function onData(string $data, $socket, \obray\SocketServer $server): void
    {
        $server->qWrite($socket, $data);
    }

    public function onConnect($socket, \obray\SocketServer $server): void
    {
        print_r("Connecting...");
    }

    public function onConnected($socket, \obray\SocketServer $server): void
    {
        print_r("success\n");
    }

    public function onDisconnect($socket, \obray\SocketServer $server): void
    {
        print_r("disconnecting....");
    }

    public function onDisconnected($socket, \obray\SocketServer $server): void
    {
        print_r("success\n");
    }
}