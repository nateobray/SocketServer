<?php
namespace obray\base;

class SocketServerBaseHandler implements \obray\interfaces\SocketServerHandlerInterface
{

    public function onStart(\obray\SocketServer $connection): void
    {
        return;
    }

    public function onData(string $data, int $readLength, \obray\interfaces\SocketConnectionInterface $connection)
    {
        // write some data to the socket
        $connection->qWrite($data);
        // return false, don't read any more (discard remaining data on the socket)
        return false;
    }

    public function onConnect(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("Connecting...");
    }

    public function onConnected(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("success\n");
    }

    public function onConnectFailed(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("failed!\n");
    }

    public function onWriteFailed($data, \obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("Write failed!\n");
        $connection->disconnect();
    }

    public function onReadFailed(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("Read failed!\n");
        $server->disconnect($socket);
    }

    public function onDisconnect(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("disconnecting....");
    }

    public function onDisconnected(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        print_r("success\n");
    }
}