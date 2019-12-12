<?php

namespace obray\interfaces;

interface EventLoopInterface 
{
    public function run();
    public function watchStreamSocket($socket, callable $callback, $data);
    public function watchTimer(float $delay, float $interval, callable $callback, $data);
}