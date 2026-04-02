<?php

declare(strict_types=1);

/**
 * Span Exception
 *
 * Thrown when span operations fail due to invalid state.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Exception;

final class SpanException extends TraceLogException
{
    /**
     * Create for when no active span exists.
     *
     * @return self Exception instance
     */
    public static function noActiveSpan(): self
    {
        return new self('No active span');
    }

    /**
     * Create for when no root span exists.
     *
     * @return self Exception instance
     */
    public static function noRootSpan(): self
    {
        return new self('No root span');
    }
}
