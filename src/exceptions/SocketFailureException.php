<?php
namespace obray\exceptions;

class SocketFailureException extends \Exception
{
    public function __construct($message="Socket server connection has failed.", $code=500, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}