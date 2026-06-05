<?php
namespace obray\handlers;

class EchoServer extends \obray\base\SocketServerBaseHandler
{
    public function onData(string $data, int $readLength, \obray\interfaces\SocketConnectionInterface $connection)
    {
        $connection->qWrite($data);
        return false;
    }
}
