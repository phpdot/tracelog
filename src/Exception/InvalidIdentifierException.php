<?php

declare(strict_types=1);

/**
 * Invalid Identifier Exception
 *
 * Thrown when a trace ID or span ID has an invalid format.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Exception;

final class InvalidIdentifierException extends TraceLogException
{
    /**
     * Create for an invalid trace ID.
     *
     * @param string $id The invalid ID string
     * @return self Exception instance
     */
    public static function traceId(string $id): self
    {
        return new self("Invalid trace ID format: expected 32 hex characters or UUID format, got '{$id}'");
    }

    /**
     * Create for an invalid span ID.
     *
     * @param string $id The invalid ID string
     * @return self Exception instance
     */
    public static function spanId(string $id): self
    {
        return new self("Invalid span ID format: expected 16 hex characters, got '{$id}'");
    }

    /**
     * Create for an invalid traceparent header.
     *
     * @param string $header The invalid header string
     * @return self Exception instance
     */
    public static function traceparent(string $header): self
    {
        return new self("Invalid traceparent header format: '{$header}'");
    }
}
