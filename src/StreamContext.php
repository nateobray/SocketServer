<?php
namespace obray;

class StreamContext
{
    private $context;
    public function __construct($context=array())
    {
        $this->context = stream_context_create( $context );
    }

    public function get()
    {
        return $this->context;
    }
}