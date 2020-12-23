<?php

namespace obray\interfaces;

interface SocketServerHandlerInterface 
{
    public function onStart(\obray\SocketServer $connection): void;
    public function onData(string $data, \obray\interfaces\SocketConnectionInterface $connection): void;
    public function onConnect(\obray\interfaces\SocketConnectionInterface $connection): void;
    public function onConnected(\obray\interfaces\SocketConnectionInterface $connection): void;
    public function onConnectFailed(\obray\interfaces\SocketConnectionInterface $connection): void;
    public function onWriteFailed($data, \obray\interfaces\SocketConnectionInterface $connection): void;
    public function onReadFailed(\obray\interfaces\SocketConnectionInterface $connection): void;
    public function onDisconnect(\obray\interfaces\SocketConnectionInterface $connection): void;
    public function onDisconnected(\obray\interfaces\SocketConnectionInterface $connection): void;
}