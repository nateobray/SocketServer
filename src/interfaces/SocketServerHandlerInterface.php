<?php

namespace obray\interfaces;

interface SocketServerHandlerInterface 
{
    public function onData(string $data, $socket, \obray\SocketServer $server): void;
    public function onConnect($socket, \obray\SocketServer $server): void;
    public function onConnected($socket, \obray\SocketServer $server): void;
    public function onConnectFailed($socket, \obray\SocketServer $server): void;
    public function onWriteFailed($data, $socket, \obray\SocketServer $server): void;
    public function onReadFailed($socket, \obray\SocketServer $server): void;
    public function onDisconnect($socket, \obray\SocketServer $server): void;
    public function onDisconnected($socket, \obray\SocketServer $server): void;
}