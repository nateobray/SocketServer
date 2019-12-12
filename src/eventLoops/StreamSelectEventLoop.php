<?php
namespace obray\eventLoops;

class StreamSelectEventLoop implements \obray\interfaces\EventLoopInterface
{
    private $sockets;
    private $socketWatchers = [];
    private $socketWatchersSockets = [];
    private $timerWatchers = [];

    public function __construct($socket, $timeout)
    {
        $this->socket = $socket;
        $this->selectTimeout = $timeout;
    }

    public function run()
    {
        $sockets = [];
        while(true){
            $changed = $this->socketWatchersSockets; $null = NULL;
            $changed[] = $this->socket;
            stream_select( $changed, $null, $null, 0, 0);

            // call callbacks for changed sockets
            forEach($changed as $socket){
                $index = \array_search($socket, $this->socketWatchersSockets);
                if($this->socketWatchers[$index]->isActive === false){
                    unset($this->socketWatchers[$index]);
                    unset($this->socketWatchersSockets[$index]);
                    continue;
                }
                if($index !== false){
                    $this->socketWatchers[$index]->invoke();
                }
            }

            // call callback for timmer watchers
            forEach($this->timerWatchers as $index => $watcher){
                if($watcher->isActive === false){
                    unset($this->timerWatchers[$index]);
                    continue;
                }
                if($watcher->shouldInvoke()){
                    $watcher->invoke();
                }
            }
            time_nanosleep(0, 100);
        }
        return true;
    }

    public function watchStreamSocket($socket, callable $callback, $data)
    {
        $watcher = new \obray\eventLoops\Watcher($data);
        $watcher->setCallback($callback);
        $watcher->start();
        $this->socketWatchers[] = $watcher;
        $this->socketWatchersSockets[] = $socket;
        return $watcher;
    }

    public function watchTimer(float $delay, float $interval, callable $callback, $data)
    {
        $watcher = new \obray\eventLoops\Watcher($data);
        $watcher->setCallback($callback);
        $watcher->setTimer($delay, $interval);
        $watcher->start();
        $this->timerWatchers[] = $watcher;
        return $watcher;
    }
}