<?php

namespace obray\interfaces;

interface SocketServerHandlerInterface 
{
    public function onData(string $data, \obray\SocketConnection $connection): void;
    public function onConnect(\obray\SocketConnection $connection): void;
    public function onConnected(\obray\SocketConnection $connection): void;
    public function onConnectFailed(\obray\SocketConnection $connection): void;
    public function onWriteFailed($data, \obray\SocketConnection $connection): void;
    public function onReadFailed(\obray\SocketConnection $connection): void;
    public function onDisconnect(\obray\SocketConnection $connection): void;
    public function onDisconnected(\obray\SocketConnection $connection): void;
}