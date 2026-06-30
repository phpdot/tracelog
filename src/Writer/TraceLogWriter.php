<?php

declare(strict_types=1);

/**
 * TraceLog Writer
 *
 * The rich, encrypted-file backend for the phpdot observability engine. It
 * implements the engine's {@see WriterInterface} export boundary: it owns no trace
 * identity and mints no ids — it only takes an already-correlated record
 * (a log line or a finished span snapshot, as a flat `array<string, mixed>`),
 * normalizes it into the shape the channel handlers/formatters expect, and
 * writes it to the per-channel {@see StreamHandler} via the {@see ChannelManager}.
 *
 * Normalization maps the engine record onto the handler record shape:
 *   - `timestamp` float (microtime) -> ISO-8601 string,
 *   - `level` PSR string -> integer level + `level_name`,
 *   - log records route to the `app` channel, finished spans to the `trace` channel.
 *
 * Trace correlation fields (`trace_id`, `span_id`) are always written in
 * plaintext at the top level so the output stays queryable.
 *
 * A record flagged sensitive (`secure`/`sensitive`) has its message AND context
 * encrypted together with the {@see ChaChaEncryptor}. This is fail-closed: a
 * sensitive record that cannot be protected (no encryptor, or encryption fails)
 * is dropped, never written in plaintext. Export never throws — a failure in the
 * write path must not crash the application or the coroutine-end span flush.
 *
 * Stateless singleton; binds as the default {@see WriterInterface}, overriding the
 * engine's NullWriter whenever tracelog is installed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Writer;

use DateTimeImmutable;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Contracts\Logs\WriterInterface;
use PHPdot\TraceLog\Encryption\EncryptorInterface;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogLevel;
use Throwable;

#[Singleton]
final class TraceLogWriter implements WriterInterface
{
    /**
     * Create a TraceLog writer.
     *
     * The channel manager (built from config in the consumer's binding closure)
     * resolves a dedicated stream handler per channel, and the optional encryptor
     * enables the sensitive-record encrypt path.
     *
     * @param ChannelManager $channelManager Resolves the stream handler per channel.
     * @param EncryptorInterface|null $encryptor Optional encryptor for sensitive records.
     */
    public function __construct(
        private readonly ChannelManager $channelManager,
        private readonly ?EncryptorInterface $encryptor = null,
    ) {}

    /**
     * Export a single record — a log line or a finished span snapshot.
     *
     * @param array<string, mixed> $record The engine record to export.
     */
    public function write(array $record): void
    {
        // Export must never crash the application or the coroutine-end flush that
        // calls it: any unexpected failure drops the record rather than throwing.
        try {
            $isSpan = ($record['type'] ?? null) === 'span';

            $normalized = $isSpan
                ? $this->normalizeSpan($record)
                : $this->normalizeLog($record);

            // null == a sensitive record we could not protect — dropped (fail-closed).
            if ($normalized === null) {
                return;
            }

            $this->channelManager->getHandler($this->channelName($record))->handle($normalized);
        } catch (Throwable) {
            // Intentionally swallowed — logging/tracing must not bring down the caller.
        }
    }

    /**
     * Normalize an engine log record into the handler/formatter record shape.
     *
     * @param array<string, mixed> $record The engine log record.
     *
     * @return array<string, mixed>|null The normalized record, or null if dropped (fail-closed).
     */
    private function normalizeLog(array $record): ?array
    {
        $level   = LogLevel::fromPsr($this->toString($record['level'] ?? null, 'debug'));
        $message = $this->toString($record['message'] ?? null);
        $context = $this->toArray($record['context'] ?? null);

        $protected = $this->protect($message, $context, $this->isSensitive($record));

        if ($protected === null) {
            return null;
        }

        return [
            'timestamp'  => $this->toIso($this->toFloat($record['timestamp'] ?? null)),
            'level'      => $level,
            'level_name' => LogLevel::name($level),
            'message'    => $protected['message'],
            'channel'    => $this->channelName($record),
            'trace_id'   => $this->toString($record['trace_id'] ?? null),
            'span_id'    => $this->toString($record['span_id'] ?? null),
            'context'    => $protected['context'],
        ];
    }

    /**
     * Normalize a finished span snapshot into the handler/formatter record shape.
     *
     * The span name becomes the message; span timing/status/attributes/events are
     * carried in the context so the formatter renders the full snapshot.
     *
     * @param array<string, mixed> $record The engine span record.
     *
     * @return array<string, mixed>|null The normalized record, or null if dropped (fail-closed).
     */
    private function normalizeSpan(array $record): ?array
    {
        $name   = $this->toString($record['name'] ?? null, 'span');
        $status = $this->toString($record['status'] ?? null);
        $level  = strtolower($status) === 'error' ? LogLevel::ERROR : LogLevel::INFO;

        $endedAt   = $this->toFloat($record['ended_at'] ?? null);
        $startedAt = $this->toFloat($record['started_at'] ?? null);
        $stamp     = $endedAt > 0.0 ? $endedAt : $startedAt;

        $context = [
            'parent_span_id' => $this->toString($record['parent_span_id'] ?? null),
            'kind'           => $this->toString($record['kind'] ?? null),
            'started_at'     => $startedAt,
            'ended_at'       => $endedAt,
            'duration_ms'    => $this->toFloat($record['duration_ms'] ?? null),
            'status'         => $status,
            'status_message' => $this->toString($record['status_message'] ?? null),
            'attributes'     => $this->toArray($record['attributes'] ?? null),
            'events'         => $this->toArray($record['events'] ?? null),
        ];

        $protected = $this->protect($name, $context, $this->isSensitive($record));

        if ($protected === null) {
            return null;
        }

        return [
            'timestamp'  => $this->toIso($stamp),
            'level'      => $level,
            'level_name' => LogLevel::name($level),
            'message'    => $protected['message'],
            'channel'    => $this->channelName($record),
            'trace_id'   => $this->toString($record['trace_id'] ?? null),
            'span_id'    => $this->toString($record['span_id'] ?? null),
            'context'    => $protected['context'],
        ];
    }

    /**
     * Determine whether a record is marked sensitive and must be encrypted.
     *
     * @param array<string, mixed> $record The record to inspect.
     *
     * @return bool True if the record carries a truthy `secure` or `sensitive` marker.
     */
    private function isSensitive(array $record): bool
    {
        return ($record['secure'] ?? false) === true
            || ($record['sensitive'] ?? false) === true;
    }

    /**
     * Apply fail-closed protection to a record's message and context.
     *
     * For a non-sensitive record the message and context pass through unchanged.
     * For a sensitive record the message and context are encrypted together; if no
     * encryptor is configured or encryption fails, null is returned so the caller
     * drops the record rather than writing plaintext.
     *
     * @param string $message The plaintext message.
     * @param array<array-key, mixed> $context The plaintext context.
     * @param bool $sensitive Whether the record must be protected.
     *
     * @return array{message: string, context: array<array-key, mixed>}|null
     *                                                                       The (possibly encrypted) message/context, or null to drop the record.
     */
    private function protect(string $message, array $context, bool $sensitive): ?array
    {
        if (!$sensitive) {
            return ['message' => $message, 'context' => $context];
        }

        // Fail-closed: a sensitive record we cannot protect is dropped, never plaintext.
        if ($this->encryptor === null) {
            return null;
        }

        try {
            // Encrypt the message AND context together — context is where structured
            // logging stores the actual secrets. Any failure drops the record.
            $payload = json_encode(
                ['message' => $message, 'context' => $context],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            $ciphertext = $this->encryptor->encrypt($payload);
        } catch (Throwable) {
            return null;
        }

        return ['message' => $ciphertext, 'context' => ['encrypted' => true]];
    }

    /**
     * Convert a microtime float into an ISO-8601 timestamp string.
     *
     * @param float $timestamp Seconds since the epoch (microtime), or <= 0 for "now".
     *
     * @return string ISO-8601 timestamp with microseconds and offset.
     */
    private function toIso(float $timestamp): string
    {
        if ($timestamp <= 0.0) {
            return (new DateTimeImmutable())->format('Y-m-d\TH:i:s.uP');
        }

        // %.6F is locale-independent; %f would honor LC_NUMERIC and could emit a
        // comma that breaks the 'U.u' parse.
        $parsed = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp));

        $stamp = $parsed !== false ? $parsed : new DateTimeImmutable();

        return $stamp->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * Coerce a mixed value into a string, falling back to a default.
     *
     * @param mixed $value The value to coerce.
     * @param string $default The fallback when the value is not stringable scalar.
     *
     * @return string The coerced string.
     */
    private function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Coerce a mixed value into a float, falling back to 0.0.
     *
     * @param mixed $value The value to coerce.
     *
     * @return float The coerced float.
     */
    private function toFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * Coerce a mixed value into an array, falling back to an empty array.
     *
     * @param mixed $value The value to coerce.
     *
     * @return array<array-key, mixed> The coerced array.
     */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The channel a record routes to — its `channel` field, or 'app' by default.
     *
     * @param array<string, mixed> $record The engine record.
     *
     * @return string The channel name.
     */
    private function channelName(array $record): string
    {
        return $this->toString($record['channel'] ?? null, 'app');
    }
}
