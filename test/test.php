<?php
require_once "vendor/autoload.php";

$streamContext = new \obray\StreamContext();
$socketServer = new \obray\SocketServer('tcp', 'localhost', 9292, $streamContext);
$socketServer->registerHandler(new \obray\handlers\EchoServer());
$socketServer->start();
