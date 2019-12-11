<?php

echo "EV Test\n";

// Create and start timer firing after 2 seconds
$w1 = new EvTimer(2, 0, function () {
    echo "2 seconds elapsed\n";
});

// run even loop
echo "Running event loop.\n";
EV::run();