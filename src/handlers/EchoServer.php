<?php
namespace obray\handlers;

class EchoServer extends \obray\base\SocketServerBaseHandler
{
    public function onData(string $data, $socket, \obray\SocketServer $server): void
    {
        $server->qWrite($socket, $data);
    }

    public function onConnect($socket, \obray\SocketServer $server): void
    {
        print_r("Connecting...");
    }

    public function onConnectFailed($socket, \obray\SocketServer $server): void
    {
        print_r("failed!\n");
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