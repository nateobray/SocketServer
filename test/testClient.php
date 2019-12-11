<?php
require_once "vendor/autoload.php";


$start = \microtime(true);
$isParent = true;
$dataToWrite = "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?";
//$dataToWrite .= $dataToWrite;

for($j=0;$j<500;++$j){
    $pid = pcntl_fork();
    if ($pid == -1) {
        // problem forking
        die('could not fork');
   } else if ($pid) {
        // we are the parent
        //print_r("Created child ".$pid."\n");
   } else {    
        // we are the child
        $isParent = false;
        $pid = getmypid();
        //print_r("Connecting socket\n");
        $resource = stream_socket_client("tcp://localhost:9292");
        if($resource == false) exit();
        for($i=0;$i<100;++$i){
            //print_r("Writing to socket\n");
            $bytesWritten = fwrite($resource, "pid: " . $pid . "-" . $i . ": " . $dataToWrite . $dataToWrite . $dataToWrite . $dataToWrite . $dataToWrite . $dataToWrite . "\n\n");
            //print_r("\nData Sent (".$bytesWritten." bytes): " . $dataToWrite . "|\n");
            //print_r("reading from socket\n");
            $data = fread($resource, 8192);
            if(!empty($data)){
                //print_r("\nData Received (".mb_strlen($data)." bytes): " . $data . "|\n");
            }
        }
        exit();
   }
   
}

pcntl_wait($status); //Protect against Zombie children
$end = \microtime(true);
if($isParent){
    $elapsed = $end - $start;
    print_r("\ntotal time elapsed: " . $elapsed . "s\n");
}


