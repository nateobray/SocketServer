<?php
namespace obray;

class StreamContext
{
    private $context = NULL;
    private $isEncrypted = false;

    public function __construct(array $context=[])
    {
        if(!empty($context['ssl'])) $this->isEncrypted = true;
        $this->context = stream_context_create($context);
    }

    public function get()
    {
        return $this->context;
    }

    public function isEncrypted()
    {
        return $this->isEncrypted;
    }
}
