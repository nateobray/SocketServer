<?php
require_once "vendor/autoload.php";


<<<<<<< HEAD
=======
$start = \microtime(true);
$isParent = true;
$dataToWrite = "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?";

>>>>>>> 7244b60ab56104fcce18d1fedb3b27b9bb979815

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
<<<<<<< HEAD
        $pid = getmypid();
        $resource = stream_socket_client("tcp://localhost:9292");
        for($i=0;$i<1000;++$i){
            $bytesWritten = fwrite($resource, "test data for ".$pid." message # " . $i);
            //print_r($bytesWritten . " bytes written to ".$pid.".\n");
            usleep(25000);
=======
        $isParent = false;
        $pid = getmypid();
        $resource = stream_socket_client("tcp://54.184.98.194:9292");
        if($resource == false) exit();
        for($i=0;$i<2000;++$i){
            $bytesWritten = fwrite($resource, "test data for ".$pid." message # " . $i . " - " . $dataToWrite);
            //print_r($bytesWritten . " bytes written to ".$pid.".\n");
            usleep(1000);
>>>>>>> 7244b60ab56104fcce18d1fedb3b27b9bb979815
            $data = fread($resource, 8192);
            if(!empty($data)){
                //print_r('|'.$data."|\n");
            }
        }
        exit();
   }
   
}
<<<<<<< HEAD
pcntl_wait($status); //Protect against Zombie children
=======


pcntl_wait($status); //Protect against Zombie children
$end = \microtime(true);
if($isParent){
    $elapsed = $end - $start;
    print_r("total time elapsed: " . $elapsed . "s\n");
}
>>>>>>> 7244b60ab56104fcce18d1fedb3b27b9bb979815
