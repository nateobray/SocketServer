<?php
namespace obray\handlers;

class EchoServer extends \obray\base\SocketServerBaseHandler
{
    public function onData(string $data, \obray\SocketConnection $connection): void
    {
        $connection->qWrite($data);
    }
}