<?php

namespace obray\interfaces;

interface SocketConnectionInterface
{
    public function run();
    public function qWrite(string $data);
    public function qDisconnect();
    public function isConnected();
}
