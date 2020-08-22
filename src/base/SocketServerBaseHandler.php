<?php
namespace obray\base;

class SocketServerBaseHandler implements \obray\interfaces\SocketServerHandlerInterface
{
    public function onData(string $data, \obray\SocketConnection $connection): void
    {
        $connection->qWrite($data);
    }

    public function onConnect(\obray\SocketConnection $connection): void
    {
        print_r("Connecting...");
    }

    public function onConnected(\obray\SocketConnection $connection): void
    {
        print_r("success\n");
    }

    public function onConnectFailed(\obray\SocketConnection $connection): void
    {
        print_r("failed!\n");
    }

    public function onWriteFailed($data, \obray\SocketConnection $connection): void
    {
        print_r("Write failed!\n");
        $connection->disconnect();
    }

    public function onReadFailed(\obray\SocketConnection $connection): void
    {
        print_r("Read failed!\n");
        $server->disconnect($socket);
    }

    public function onDisconnect(\obray\SocketConnection $connection): void
    {
        print_r("disconnecting....");
    }

    public function onDisconnected(\obray\SocketConnection $connection): void
    {
        print_r("success\n");
    }
}