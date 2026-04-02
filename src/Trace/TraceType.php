<?php

declare(strict_types=1);

/**
 * Trace Type
 *
 * Backed integer enum representing the type of trace origin.
 * Bit-packed into the TraceId for efficient storage and identification.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Trace;

enum TraceType: int
{
    case UNKNOWN = 0b000;
    case HTTP    = 0b001;
    case CLI     = 0b010;
    case QUEUE   = 0b011;
    case CRON    = 0b100;
    case STREAM  = 0b101;

    /**
     * Detect the trace type from the current SAPI.
     *
     * @return self The detected trace type
     */
    public static function detect(): self
    {
        if (PHP_SAPI === 'cli') {
            return self::CLI;
        }

        return self::HTTP;
    }
}
