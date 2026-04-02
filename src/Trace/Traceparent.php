<?php

declare(strict_types=1);

/**
 * Traceparent
 *
 * Value object representing a W3C Trace Context traceparent.
 * Contains trace_id and span_id for distributed tracing correlation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Trace;

use PHPdot\TraceLog\Exception\InvalidIdentifierException;

final class Traceparent
{
    /**
     * Create a new Traceparent instance.
     *
     * @param string $traceId The trace identifier (32 hex chars or UUID format)
     * @param string $spanId The span identifier (16 hex characters)
     */
    public function __construct(
        private readonly string $traceId,
        private readonly string $spanId,
    ) {}

    /**
     * Parse an incoming W3C Trace Context header.
     *
     * @param string $header The traceparent header value (e.g. "00-{trace_id}-{span_id}-01")
     *
     *
     * @throws InvalidIdentifierException If the header format is invalid
     * @return self New Traceparent instance
     */
    public static function fromHeader(string $header): self
    {
        $parts = explode('-', $header);

        if (count($parts) !== 4) {
            throw InvalidIdentifierException::traceparent($header);
        }

        $hexTraceId = $parts[1];
        $traceId    = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hexTraceId, 0, 8),
            substr($hexTraceId, 8, 4),
            substr($hexTraceId, 12, 4),
            substr($hexTraceId, 16, 4),
            substr($hexTraceId, 20),
        );

        return new self($traceId, $parts[2]);
    }

    /**
     * Reconstruct from stored array data.
     *
     * @param array{trace_id: string, span_id: string} $data The stored data
     *
     * @return self New Traceparent instance
     */
    public static function fromArray(array $data): self
    {
        return new self($data['trace_id'], $data['span_id']);
    }

    /**
     * Get the trace identifier.
     *
     * @return string The trace ID
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * Get the span identifier.
     *
     * @return string The span ID
     */
    public function getSpanId(): string
    {
        return $this->spanId;
    }

    /**
     * Format as W3C Trace Context header value.
     *
     * @return string The formatted traceparent header (00-{trace_id}-{span_id}-01)
     */
    public function toHeader(): string
    {
        $hexTraceId = str_replace('-', '', $this->traceId);

        return sprintf('00-%s-%s-01', $hexTraceId, $this->spanId);
    }

    /**
     * Serialize to array for storage.
     *
     * @return array{trace_id: string, span_id: string}
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id'  => $this->spanId,
        ];
    }

    /**
     * String representation returns the W3C header format.
     *
     * @return string The traceparent header
     */
    public function __toString(): string
    {
        return $this->toHeader();
    }
}
