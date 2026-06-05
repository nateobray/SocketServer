<?php

spl_autoload_register(function ($class) {
    $prefix = 'obray\\';
    if(strpos($class, $prefix) !== 0){
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $file = __DIR__ . '/../src/' . $relative;
    if(file_exists($file)){
        require $file;
    }
});

if(!function_exists('pcntl_fork')){
    fwrite(STDERR, "pcntl extension is required for this smoke test.\n");
    exit(1);
}

$host = '127.0.0.1';
$port = findOpenPort($host);
$pid = pcntl_fork();

if($pid === -1){
    fwrite(STDERR, "Unable to fork smoke test server.\n");
    exit(1);
}

if($pid === 0){
    $server = new \obray\SocketServer('tcp', $host, $port);
    $server->showServerStatus(false);
    $server->start(new \obray\handlers\EchoServer());
    exit(0);
}

try {
    waitForServer($host, $port);
    $socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
    if(!$socket){
        throw new \RuntimeException("Unable to connect to smoke test server: {$errstr}");
    }
    stream_set_timeout($socket, 2);

    $message = "hello socket server\n";
    fwrite($socket, $message);
    $response = fread($socket, strlen($message));
    if($response !== $message){
        throw new \RuntimeException("Echo mismatch: expected " . var_export($message, true) . ", got " . var_export($response, true));
    }

    fclose($socket);
    echo "socket smoke ok on {$host}:{$port}\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    posix_kill($pid, SIGTERM);
    pcntl_waitpid($pid, $status);
}

function findOpenPort(string $host): int
{
    $socket = stream_socket_server("tcp://{$host}:0", $errno, $errstr);
    if(!$socket){
        throw new \RuntimeException("Unable to allocate a smoke test port: {$errstr}");
    }
    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int)substr(strrchr($name, ':'), 1);
}

function waitForServer(string $host, int $port): void
{
    $deadline = microtime(true) + 3;
    do {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 0.1);
        if($socket){
            fclose($socket);
            return;
        }
        usleep(10000);
    } while(microtime(true) < $deadline);

    throw new \RuntimeException("Smoke test server did not start.");
}
