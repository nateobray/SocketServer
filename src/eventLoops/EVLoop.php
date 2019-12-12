<?php
namespace obray\eventLoops;

class EVLoop implements \obray\interfaces\EventLoopInterface
{
    public function run(): void
    {
        \EV::run();
    }

    public function watchStreamSocket($socket, callable $callback, $data)
    {
        return new \EvIo($socket, \Ev::READ, $callback, $data);
    }

    public function watchTimer(float $delay, float $interval, callable $callback, $data)
    {
        return new \EvTimer($delay, $interval, $callback, $data);
    }
}