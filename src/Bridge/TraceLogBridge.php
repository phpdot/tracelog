<?php

declare(strict_types=1);

/**
 * TraceLog Bridge
 *
 * Implements ContextInterface to enrich log records with trace and span data
 * from the current active span on the span stack.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Bridge;

use PHPdot\TraceLog\Log\Context\ContextInterface;
use PHPdot\TraceLog\Span\SpanStack;

final class TraceLogBridge implements ContextInterface
{
    /**
     * Create a TraceLog bridge.
     *
     * @param SpanStack $spanStack The span stack to read current span from
     */
    public function __construct(
        private readonly SpanStack $spanStack,
    ) {}

    /**
     * Return trace context data from the current span.
     *
     * Returns an empty array if no span is active.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $span = $this->spanStack->current();

        if ($span === null) {
            return [];
        }

        return [
            'trace_id' => $span->getTraceparent()->getTraceId(),
            'span_id'  => $span->getTraceparent()->getSpanId(),
            'tags'     => $span->getTags(),
        ];
    }
}
