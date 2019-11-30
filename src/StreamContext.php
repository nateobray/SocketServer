<?php
namespace obray;

class StreamContext
{
    private $context = NULL;

    public function __construct(array $context=[])
    {
        $this->context = stream_context_create($context);
    }

    public function get()
    {
        return $this->context;
    }
}