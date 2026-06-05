<?php
namespace obray\base;

class SocketServerBaseHandler implements \obray\interfaces\SocketServerHandlerInterface
{
    private $logger;

    public function __construct(callable $logger = null)
    {
        $this->logger = $logger;
    }

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
        $this->log("Connecting.");
    }

    public function onConnected(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Connected.");
    }

    public function onConnectFailed(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Connection failed.");
    }

    public function onWriteFailed($data, \obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Write failed.");
        $connection->disconnect();
    }

    public function onReadFailed(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Read failed.");
        $connection->disconnect();
    }

    public function onDisconnect(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Disconnecting.");
    }

    public function onDisconnected(\obray\interfaces\SocketConnectionInterface $connection): void
    {
        $this->log("Disconnected.");
    }

    private function log(string $message): void
    {
        if($this->logger !== null){
            ($this->logger)($message);
        }
    }
}
