<?php
require_once "vendor/autoload.php";

$streamContext = new \obray\StreamContext();
$socketServer = new \obray\SocketServer('tcp', '172.31.36.192', 8000, $streamContext);
$socketServer->start(new \obray\handlers\EchoServer());
