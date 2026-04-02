<?php

declare(strict_types=1);

/**
 * Span Identifier
 *
 * Generates unique 64-bit span identifiers as 16 hex characters.
 * W3C Trace Context compliant. Timestamp-sortable with monotonic counter.
 *
 * Layout: [48 bits timestamp_ms][16 bits counter/random]
 * Counter resets each millisecond with a random starting value.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Trace;

use InvalidArgumentException;

final class SpanId
{
    private static int $lastTimestamp = 0;
    private static int $counter = 0;

    /**
     * @param string $hex 16-character lowercase hex string
     * @param int $timestamp Unix timestamp in milliseconds
     */
    private function __construct(
        private readonly string $hex,
        private readonly int $timestamp,
    ) {}

    /**
     * Generate a new span identifier.
     *
     * Uses a monotonic counter within the same millisecond to guarantee
     * uniqueness and sortability under rapid generation.
     *
     * @return self New SpanId instance
     */
    public static function generate(): self
    {
        $timestamp = (int) (microtime(true) * 1000);

        if ($timestamp !== self::$lastTimestamp) {
            self::$lastTimestamp = $timestamp;
            self::$counter = random_int(0, 0xFFFF);
        } else {
            self::$counter++;
            if (self::$counter > 0xFFFF) {
                while ($timestamp === self::$lastTimestamp) {
                    $timestamp = (int) (microtime(true) * 1000);
                }
                self::$lastTimestamp = $timestamp;
                self::$counter = random_int(0, 0xFFFF);
            }
        }

        $bytes = pack('J', $timestamp);
        $bytes = substr($bytes, 2, 6) . pack('n', self::$counter);
        $hex = bin2hex($bytes);

        return new self($hex, $timestamp);
    }

    /**
     * Parse a span ID from a 16-character hex string.
     *
     * @param string $hex 16 hex characters
     *
     * @throws InvalidArgumentException If format is invalid
     * @return self SpanId instance
     */
    public static function fromString(string $hex): self
    {
        $hex = strtolower($hex);

        if (strlen($hex) !== 16 || preg_match('/^[0-9a-f]{16}$/', $hex) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid SpanId format: expected 16 hex characters, got "%s"', $hex),
            );
        }

        $timestamp = (int) hexdec(substr($hex, 0, 12));

        return new self($hex, $timestamp);
    }

    /**
     * Get the span ID as a 16-character hex string.
     *
     * @return string 16 lowercase hex characters
     */
    public function id(): string
    {
        return $this->hex;
    }

    /**
     * Get the embedded timestamp in milliseconds.
     *
     * @return int Unix timestamp in milliseconds
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * String representation returns the hex string.
     *
     * @return string 16 hex characters
     */
    public function __toString(): string
    {
        return $this->hex;
    }
}
