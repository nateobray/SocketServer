<?php
require_once "vendor/autoload.php";



for($j=0;$j<1000;++$j){
    $pid = pcntl_fork();
    if ($pid == -1) {
        // problem forking
        die('could not fork');
   } else if ($pid) {
        // we are the parent
        //print_r("Created child ".$pid."\n");
   } else {    
        // we are the child
        $pid = getmypid();
        $resource = stream_socket_client("tcp://localhost:9292");
        for($i=0;$i<1000;++$i){
            $bytesWritten = fwrite($resource, "test data for ".$pid." message # " . $i);
            //print_r($bytesWritten . " bytes written to ".$pid.".\n");
            usleep(25000);
            $data = fread($resource, 8192);
            if(!empty($data)){
                //print_r('|'.$data."|\n");
            }
        }
        exit();
   }
   
}
pcntl_wait($status); //Protect against Zombie children
