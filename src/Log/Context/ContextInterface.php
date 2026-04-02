<?php

declare(strict_types=1);

/**
 * Context Interface
 *
 * Contract for log context enrichment providers.
 * Implementations supply additional key-value pairs merged into every log record.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Log\Context;

interface ContextInterface
{
    /**
     * Return context data as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
