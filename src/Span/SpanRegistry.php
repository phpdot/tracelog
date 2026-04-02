<?php

declare(strict_types=1);

/**
 * Span Registry
 *
 * Stores and manages spans throughout their lifecycle.
 * Handles cleanup of completed spans to prevent memory leaks.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Span;

final class SpanRegistry
{
    /**
     * @var array<string, Span>
     */
    private array $active = [];

    /**
     * @var array<string, Span>
     */
    private array $completed = [];

    /**
     * Create a span registry.
     *
     * @param int $maxCompleted Maximum number of completed spans to retain
     */
    public function __construct(
        private readonly int $maxCompleted = 100,
    ) {}

    /**
     * Add a span to the registry as active.
     *
     * @param Span $span The span to add
     */
    public function add(Span $span): void
    {
        $spanId                = $span->getTraceparent()->getSpanId();
        $this->active[$spanId] = $span;
    }

    /**
     * Get a span by ID from active or completed storage.
     *
     * @param string $spanId The span ID
     *
     * @return Span|null The span or null if not found
     */
    public function get(string $spanId): ?Span
    {
        return $this->active[$spanId] ?? $this->completed[$spanId] ?? null;
    }

    /**
     * Mark a span as completed and move from active to completed storage.
     *
     * @param Span $span The span to mark completed
     */
    public function markCompleted(Span $span): void
    {
        $spanId = $span->getTraceparent()->getSpanId();

        if (isset($this->active[$spanId])) {
            unset($this->active[$spanId]);
            $this->completed[$spanId] = $span;
        }

        $this->trim();
    }

    /**
     * Get all active spans.
     *
     * @return array<string, Span> Active spans keyed by span ID
     */
    public function getActive(): array
    {
        return $this->active;
    }

    /**
     * Get all completed spans.
     *
     * @return array<string, Span> Completed spans keyed by span ID
     */
    public function getCompleted(): array
    {
        return $this->completed;
    }

    /**
     * Clear all spans from the registry.
     */
    public function clear(): void
    {
        $this->active    = [];
        $this->completed = [];
    }

    /**
     * Trim completed spans to prevent memory leaks.
     */
    private function trim(): void
    {
        if (count($this->completed) > $this->maxCompleted) {
            $this->completed = array_slice($this->completed, -$this->maxCompleted, null, true);
        }
    }
}
