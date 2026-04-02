<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Span;

use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Span\SpanStack;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanStackTest extends TestCase
{
    private function createSpan(string $spanId): Span
    {
        return new Span(
            new Traceparent('trace1', $spanId),
            null,
            'span-' . $spanId,
        );
    }

    #[Test]
    public function pushPopOrdering(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');
        $child = $this->createSpan('child');

        $stack->push($root);
        $stack->push($child);

        $popped = $stack->pop();

        self::assertSame($child, $popped);
        self::assertSame($root, $stack->current());
    }

    #[Test]
    public function rootProtectedFromPop(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');

        $stack->push($root);

        $result = $stack->pop();

        self::assertNull($result);
        self::assertSame($root, $stack->current());
    }

    #[Test]
    public function currentReturnsTop(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');
        $child = $this->createSpan('child');

        $stack->push($root);
        self::assertSame($root, $stack->current());

        $stack->push($child);
        self::assertSame($child, $stack->current());
    }

    #[Test]
    public function currentReturnsNullWhenEmpty(): void
    {
        $stack = new SpanStack();

        self::assertNull($stack->current());
    }

    #[Test]
    public function removeRemovesSpecificSpan(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');
        $child1 = $this->createSpan('child1');
        $child2 = $this->createSpan('child2');

        $stack->push($root);
        $stack->push($child1);
        $stack->push($child2);

        $stack->remove($child1);

        self::assertSame(2, $stack->depth());
        self::assertSame($child2, $stack->current());
    }

    #[Test]
    public function removeDoesNotRemoveRoot(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');

        $stack->push($root);
        $stack->remove($root);

        self::assertSame(1, $stack->depth());
        self::assertSame($root, $stack->current());
    }

    #[Test]
    public function isEmptyAndDepth(): void
    {
        $stack = new SpanStack();

        self::assertTrue($stack->isEmpty());
        self::assertSame(0, $stack->depth());

        $stack->push($this->createSpan('root'));

        self::assertFalse($stack->isEmpty());
        self::assertSame(1, $stack->depth());

        $stack->push($this->createSpan('child'));

        self::assertSame(2, $stack->depth());
    }

    #[Test]
    public function clearEmptiesStack(): void
    {
        $stack = new SpanStack();
        $stack->push($this->createSpan('root'));
        $stack->push($this->createSpan('child'));

        $stack->clear();

        self::assertTrue($stack->isEmpty());
        self::assertSame(0, $stack->depth());
        self::assertNull($stack->current());
    }

    #[Test]
    public function rootReturnsFirstPushed(): void
    {
        $stack = new SpanStack();
        $root = $this->createSpan('root');
        $child = $this->createSpan('child');

        $stack->push($root);
        $stack->push($child);

        self::assertSame($root, $stack->root());
    }

    #[Test]
    public function rootReturnsNullWhenEmpty(): void
    {
        $stack = new SpanStack();

        self::assertNull($stack->root());
    }
}
