<?php
require_once "vendor/autoload.php";


$socketServer = new \obray\SocketServer('tcp', '172.31.47.201', 9292);
//$WebSocketServer->setAWSCredentials('AKIAJAL477GZ6SVXDQOA', 'kh7IzlPaPW2jgYfi6N280+/MfUY5xBMag9i3SNNP', 'dashboards');
$socketServer->start();
