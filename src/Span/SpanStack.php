<?php

declare(strict_types=1);

/**
 * Span Stack
 *
 * Manages the hierarchy of active spans using a stack structure.
 * The root span (index 0) is protected and cannot be popped.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Span;

final class SpanStack
{
    /**
     * @var list<Span>
     */
    private array $stack = [];

    /**
     * Push a span onto the stack.
     *
     * @param Span $span The span to push
     */
    public function push(Span $span): void
    {
        $this->stack[] = $span;
    }

    /**
     * Pop the top span from the stack.
     * The root span (index 0) is never removed.
     *
     * @return Span|null The popped span or null if only root remains
     */
    public function pop(): ?Span
    {
        if (count($this->stack) <= 1) {
            return null;
        }

        return array_pop($this->stack);
    }

    /**
     * Get the current (top) span without removing it.
     *
     * @return Span|null Current span or null if empty
     */
    public function current(): ?Span
    {
        $span = end($this->stack);

        return $span === false ? null : $span;
    }

    /**
     * Remove a specific span from the stack.
     * The root span (index 0) is never removed.
     *
     * @param Span $span The span to remove
     */
    public function remove(Span $span): void
    {
        $index = array_search($span, $this->stack, true);

        if ($index !== false && $index !== 0) {
            array_splice($this->stack, $index, 1);
        }
    }

    /**
     * Get the root span (first pushed).
     *
     * @return Span|null The root span or null if empty
     */
    public function root(): ?Span
    {
        return $this->stack[0] ?? null;
    }

    /**
     * Check if the stack is empty.
     *
     * @return bool True if empty
     */
    public function isEmpty(): bool
    {
        return $this->stack === [];
    }

    /**
     * Get the current stack depth.
     *
     * @return int Number of spans in stack
     */
    public function depth(): int
    {
        return count($this->stack);
    }

    /**
     * Clear all spans from the stack.
     */
    public function clear(): void
    {
        $this->stack = [];
    }
}
