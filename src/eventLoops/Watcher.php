<?php

namespace obray\eventLoops;

class Watcher
{
    private $callback;
    private $started = 0;
    private $lastCall = 0;
    private $delay;
    private $interval;

    public $isActive = 1;
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function start()
    {
        $this->started = microtime(true);
        $this->isActive = true;
    }

    public function stop()
    {
        $this->isActive = false;
    }

    public function invoke()
    {
        $this->lastCall = microtime(true);
        ($this->callback)($this);
    }

    public function setTimer(float $delay, float $interval)
    {
        $this->delay = $delay;
        $this->interval = $interval;
    }

    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    public function shouldInvoke(): bool
    {
        $currentTime = microtime(true);
        if($this->lastCall === 0 && $currentTime - $this->started > $this->delay){
            print_r("should invoke (first call): true\n");
            return true;
        }

        if(($currentTime - $this->lastCall) > $this->interval){
            print_r("should invoke (subsequent call) elapsed: " . ($currentTime - $this->lastCall) . "s\n");
            return true;
        }

        return false;
    }
}