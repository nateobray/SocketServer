# Changelog

## 1.1.0 - 2026-06-05

Initial stable release of `obray/socket-server`.

### Added

- Low-level TCP socket server with non-blocking reads and queued writes.
- `SocketServerHandlerInterface` callback contract.
- `SocketConnectionInterface` for queued writes, queued disconnects, immediate disconnects, and connection state checks.
- `StreamSelectEventLoop` default event loop.
- Optional EV event loop support when explicitly selected and the extension is installed.
- Timer registration via `SocketServer::watchTimer()`.
- Optional logger hook via `SocketServer::setLogger()`.
- Quiet base handler with optional logger injection.
- Echo handler.
- Local smoke test at `tools/socket_smoke.php`.
- README usage and production notes.

### Changed

- Default event loop now uses `stream_select`; EV is opt-in.
- Server status output can be disabled or routed through a logger.
- Socket read failures now call `onReadFailed()` after repeated failures instead of spinning indefinitely.
- Event loop avoids stale stream resources and uses a short blocking select timeout.
- PHP requirement is `>=8.1` for the stable 1.x line.

### Removed

- Experimental threaded connection implementation.
- Automatic `Pool`/threaded connection path.
- Library-level `exit()` calls from socket failure recovery paths.

### Fixed

- Corrected `WatcherInterface` declaration.
- Declared socket bind error fields to avoid dynamic properties on modern PHP.
- Fixed warning/error handler mask.
