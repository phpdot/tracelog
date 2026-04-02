<?php

declare(strict_types=1);

/**
 * Active Span
 *
 * Fluent wrapper for an active span providing convenient API
 * for logging, tagging, events, and creating child spans.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Span;

use BackedEnum;
use PHPdot\TraceLog\Log\PendingLog;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\TraceLog;

final class ActiveSpan
{
    /**
     * Create an ActiveSpan wrapper.
     *
     * @param Span $span The underlying span
     * @param TraceLog $traceLog The TraceLog facade
     */
    public function __construct(
        private readonly Span $span,
        private readonly TraceLog $traceLog,
    ) {}

    /**
     * Add a tag to the span.
     *
     * @param string $key Tag key
     * @param string|int|bool $value Tag value
     *
     * @return self For chaining
     */
    public function tag(string $key, string|int|bool $value): self
    {
        $this->span->addTag($key, $value);

        return $this;
    }

    /**
     * Add an event to the span.
     *
     * @param string $name Event name
     * @param array<string, mixed> $attributes Event attributes
     *
     * @return self For chaining
     */
    public function event(string $name, array $attributes = []): self
    {
        $this->span->addEvent($name, $attributes);

        return $this;
    }

    /**
     * Set the channel for this span.
     *
     * @param BackedEnum $channel The channel
     *
     * @return self For chaining
     */
    public function channel(BackedEnum $channel): self
    {
        $this->span->setChannel($channel);

        return $this;
    }

    /**
     * Log a debug message in the context of this span.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function debug(string $message, array $context = []): PendingLog
    {
        return $this->traceLog->logWithSpan('debug', $message, $this->span, $context);
    }

    /**
     * Log an info message in the context of this span.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function info(string $message, array $context = []): PendingLog
    {
        return $this->traceLog->logWithSpan('info', $message, $this->span, $context);
    }

    /**
     * Log a warning message in the context of this span.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function warning(string $message, array $context = []): PendingLog
    {
        return $this->traceLog->logWithSpan('warning', $message, $this->span, $context);
    }

    /**
     * Log an error message in the context of this span.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function error(string $message, array $context = []): PendingLog
    {
        return $this->traceLog->logWithSpan('error', $message, $this->span, $context);
    }

    /**
     * Create a child span builder.
     * The child inherits this span's channel.
     *
     * @param string $name Child span name
     *
     * @return SpanBuilder Builder for the child span
     */
    public function span(string $name): SpanBuilder
    {
        return new SpanBuilder(
            $this->traceLog,
            $name,
            $this->span->getTraceparent(),
            $this->span->getChannel(),
        );
    }

    /**
     * End the span.
     */
    public function end(): void
    {
        $this->traceLog->endSpan($this->span);
    }

    /**
     * Get the underlying span.
     *
     * @return Span The span
     */
    public function getSpan(): Span
    {
        return $this->span;
    }

    /**
     * Get this span's traceparent.
     *
     * @return Traceparent The traceparent
     */
    public function getTraceparent(): Traceparent
    {
        return $this->span->getTraceparent();
    }
}
