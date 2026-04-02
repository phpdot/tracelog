# phpdot/tracelog

Distributed tracing, structured logging, and secure log encryption for PHP.

## Install

```bash
composer require phpdot/tracelog
```

## Quick Start

```php
use PHPdot\TraceLog\TraceLog;
use PHPdot\TraceLog\Trace\TraceType;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\Channel\ChannelManager;

$log = new LogManager(new ChannelManager('/var/log/myapp'));
$tracelog = new TraceLog($log, type: TraceType::HTTP);

$tracelog->info('User logged in', ['user_id' => 42]);
```

Every log carries `trace_id` and `span_id` automatically.

---

## Architecture

```
┌─────────────────────────────────────────────┐
│                 TraceLog                     │
│        Main facade — wires everything        │
└──────┬──────────────┬──────────────┬────────┘
       │              │              │
┌──────▼──────┐ ┌─────▼─────┐ ┌─────▼──────┐
│ Span Module │ │Log Module │ │Trace Module│
│             │ │           │ │            │
│ Span        │ │LogManager │ │TraceId     │
│ ActiveSpan  │ │Channel    │ │SpanId      │
│ SpanBuilder │ │Formatter  │ │Traceparent │
│ SpanStack   │ │Handler    │ │TraceContext│
│ SpanRegistry│ │PendingLog │ │TraceType   │
└──────┬──────┘ └─────▲─────┘ └────────────┘
       │              │
  ┌────▼──────────────┘    ┌──────────────┐
  │  TraceLogBridge        │  Encryption   │
  │  (ContextInterface)    │              │
  │  Enriches logs with    │ChaChaEncryptor│
  │  trace context         │              │
  └────────────────────────┘ └─────────────┘
```

### Data Flow

```
$tracelog->info('message', ['key' => 'value'])
  │
  ├─ Gets current Span from SpanStack
  ├─ TraceLogBridge reads trace_id, span_id, tags
  ├─ LogManager enriches log record with trace context
  ├─ ChannelManager routes to channel handler
  ├─ JsonFormatter outputs structured JSON
  └─ StreamHandler writes to channel log file

Output:
{"timestamp":"...","level":200,"level_name":"INFO",
 "message":"message","trace_id":"abc...","span_id":"xyz...",
 "channel":"app","context":{"key":"value"}}
```

---

## Tracing

### TraceId

128-bit bit-packed identifiers with embedded metadata.

```php
use PHPdot\TraceLog\Trace\TraceId;
use PHPdot\TraceLog\Trace\TraceType;

$traceId = TraceId::generate(TraceType::HTTP);

$traceId->id();          // 32 hex chars
$traceId->uuid();        // UUID format
$traceId->timestamp();   // milliseconds
$traceId->type();        // TraceType::HTTP
$traceId->machineId();   // 7-bit machine identifier
$traceId->pid();         // process ID
```

### TraceType

```php
enum TraceType: int
{
    case UNKNOWN = 0;
    case HTTP    = 1;   // Web requests
    case CLI     = 2;   // Console commands
    case QUEUE   = 3;   // Job processing
    case CRON    = 4;   // Scheduled tasks
    case STREAM  = 5;   // WebSocket, SSE
}

TraceType::detect();     // CLI SAPI → CLI, else HTTP
```

### Trace Propagation

```php
// Outbound — pass to downstream service
$header = $tracelog->getTraceparent()->toHeader();
// "00-{trace_id}-{span_id}-01" (W3C Trace Context)

// Inbound — inherit from upstream
$parent = Traceparent::fromHeader($request->getHeaderLine('traceparent'));
$tracelog = TraceLog::fromTraceparent($parent, $log, type: TraceType::HTTP);

// Queue jobs
$traceparent = $tracelog->getTraceparent()->toArray();
// ... serialize into job metadata ...
$parent = Traceparent::fromArray($jobMeta['traceparent']);
$tracelog = TraceLog::fromTraceparent($parent, $log, type: TraceType::QUEUE);
```

---

## Spans

Track units of work with timing, tags, and events.

```php
$span = $tracelog->span('db.query')
    ->withTag('collection', 'users')
    ->withChannel(Channel::Database)
    ->start();

$span->event('query_sent', ['filter' => ['active' => true]]);
$span->tag('rows', 42);
$span->info('Query executed');
$span->end();
```

### Nested Spans

```php
$httpSpan = $tracelog->span('http.request')->start();

    $dbSpan = $httpSpan->span('db.query')->start();
    $dbSpan->end();

    $cacheSpan = $httpSpan->span('cache.get')->start();
    $cacheSpan->end();

$httpSpan->end();
```

---

## Logging

PSR-3 compliant. Channel-based routing.

### Channels

```php
use PHPdot\TraceLog\Log\Channel\Channel;

$tracelog->info('General log');                              // → app.log
$log->channel(Channel::Auth)->warning('Login failed');      // → auth.log
$log->channel(Channel::Database)->info('Query slow');       // → database.log
```

Built-in: `App`, `Auth`, `Database`, `Http`, `Queue`, `Mail`, `Cache`, `Security`.

Custom channels with your own enum:

```php
enum AppChannel: string
{
    case Payment = 'payment';
    case Webhook = 'webhook';
}

$log->channel(AppChannel::Payment)->info('Charge succeeded');  // → payment.log
```

### Formatters

**JsonFormatter** (production):
```json
{"timestamp":"2026-04-02T12:00:00.123456+00:00","level":200,"level_name":"INFO","message":"text","trace_id":"...","span_id":"...","channel":"auth","context":{}}
```

**TextFormatter** (development):
```
[2026-04-02 12:00:00.123] auth.INFO: text {"context":...} [trace:abc span:xyz]
```

---

## Encryption

Encrypt sensitive log messages with ChaCha20-Poly1305.

```php
use PHPdot\TraceLog\Encryption\ChaChaEncryptor;

$key = ChaChaEncryptor::generateKey();
$encryptor = new ChaChaEncryptor($key);

$log = new LogManager(new ChannelManager('/var/log/app'), encryptor: $encryptor);
$tracelog = new TraceLog($log);

$tracelog->info('User logged in');                    // plaintext
$tracelog->info('Password reset for user@x.com')->secure();  // encrypted
```

Trace fields (trace_id, span_id, channel) stay in plaintext for queryability. Only the message is encrypted.

---

## Error Handling

TraceLog never crashes the application.

- File write fails → silently skipped
- Logger creation fails → NullHandler used
- Encryption fails → written unencrypted
- Only programmer/config errors throw (invalid key, invalid trace ID format)

---

## Package Structure

```
src/
├── TraceLog.php              Main facade
├── TraceLogConfig.php        Configuration
├── Trace/
│   ├── TraceId.php           128-bit bit-packed ID
│   ├── TraceType.php         HTTP, CLI, QUEUE, CRON, STREAM
│   ├── SpanId.php            96-bit base62 ID
│   ├── Traceparent.php       W3C Trace Context
│   └── TraceContext.php      Request-level context
├── Log/
│   ├── LogManager.php        PSR-3 LoggerInterface
│   ├── LogConfig.php         Log configuration
│   ├── LogLevel.php          PSR-3 level constants
│   ├── PendingLog.php        Deferred log with encryption
│   ├── Channel/
│   │   ├── Channel.php       Default channel enum
│   │   ├── ChannelManager.php Per-channel handler routing
│   │   └── ChannelConfig.php Channel configuration
│   ├── Formatter/
│   │   ├── FormatterInterface.php
│   │   ├── JsonFormatter.php
│   │   └── TextFormatter.php
│   ├── Handler/
│   │   ├── HandlerInterface.php
│   │   ├── StreamHandler.php
│   │   └── NullHandler.php
│   └── Context/
│       └── ContextInterface.php
├── Span/
│   ├── Span.php              Span data structure
│   ├── ActiveSpan.php        Fluent span wrapper
│   ├── SpanBuilder.php       Builder pattern
│   ├── SpanStack.php         Hierarchy management
│   └── SpanRegistry.php      Active/completed storage
├── Encryption/
│   ├── EncryptorInterface.php
│   └── ChaChaEncryptor.php
└── Bridge/
    └── TraceLogBridge.php    Enriches logs with trace context
```

---

## Development

```bash
composer test        # PHPUnit (121 tests)
composer analyse     # PHPStan level 10
composer cs-fix      # PHP-CS-Fixer
composer check       # All three
```

## License

MIT
