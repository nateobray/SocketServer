# obray/socket-server

Small PHP socket server library with non-blocking reads and queued writes.

This package is intentionally low level. It accepts socket connections, invokes
a handler contract, and exposes a small timer and logging surface for libraries
or applications built on top of it. Version 1.x is intentionally single-process
and event-loop based.

## Install

```bash
composer require obray/socket-server
```

## Echo Server

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$server = new \obray\SocketServer('tcp', '127.0.0.1', 8080);
$server->registerHandler(new \obray\handlers\EchoServer());
$server->start();
```

You can also pass the handler directly:

```php
$server->start(new \obray\handlers\EchoServer());
```

## Handler Contract

Handlers implement `obray\interfaces\SocketServerHandlerInterface`.

```php
class Handler extends \obray\base\SocketServerBaseHandler
{
    public function onData(string $data, int $readLength, \obray\interfaces\SocketConnectionInterface $connection)
    {
        $connection->qWrite($data);
        return false;
    }
}
```

The base handler is quiet by default. Pass a logger if you want lifecycle output:

```php
$handler = new \obray\base\SocketServerBaseHandler(function ($message) {
    error_log($message);
});
```

Key callbacks:

- `onStart($server)` runs after the server is bound and the event loop exists.
- `onData($data, $readLength, $connection)` runs when data is read.
- `onConnect`, `onConnected`, and `onConnectFailed` cover connection setup.
- `onDisconnect` and `onDisconnected` cover connection cleanup.
- `onReadFailed` and `onWriteFailed` expose IO failures.

## Queued Writes

Use `qWrite($data)` to enqueue bytes for non-blocking writes.

Use `qDisconnect()` when a connection should close after queued writes flush.
Use `disconnect()` when the connection should close immediately.

## Timers

Register timers from `onStart()`:

```php
public function onStart(\obray\SocketServer $server): void
{
    $server->watchTimer(5, 30, function () {
        // Recurring application work.
    });
}
```

Timers are event-loop callbacks. Keep them quick and push long-running work out
to your application.

## Logging

Server status output can be disabled:

```php
$server->showServerStatus(false);
```

Or routed through a logger:

```php
$server->setLogger(function ($message) {
    error_log($message);
});
```

## Event Loop

The server uses the built-in `stream_select` event loop by default. If the EV
extension is installed, you can request it explicitly:

```php
$server->setEventLoopType(\obray\SocketServer::EV);
```

## Smoke Test

Run the local echo smoke test:

```bash
php tools/socket_smoke.php
```

## Production Notes

- Run long-lived servers under a process supervisor.
- Keep protocol-specific concerns in higher-level libraries or handlers.
- Keep application state in your handler or application container.
- Keep timer callbacks short.
- Use separate worker processes at the application/process-manager layer if you
need horizontal concurrency.
