<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Span;

use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Span\SpanRegistry;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanRegistryTest extends TestCase
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
    public function addAndGet(): void
    {
        $registry = new SpanRegistry();
        $span = $this->createSpan('span1');

        $registry->add($span);

        self::assertSame($span, $registry->get('span1'));
    }

    #[Test]
    public function getReturnsNullForUnknown(): void
    {
        $registry = new SpanRegistry();

        self::assertNull($registry->get('nonexistent'));
    }

    #[Test]
    public function markCompletedMovesToCompleted(): void
    {
        $registry = new SpanRegistry();
        $span = $this->createSpan('span1');

        $registry->add($span);
        $registry->markCompleted($span);

        // Still retrievable via get
        self::assertSame($span, $registry->get('span1'));

        // No longer in active
        self::assertArrayNotHasKey('span1', $registry->getActive());

        // In completed
        self::assertArrayHasKey('span1', $registry->getCompleted());
    }

    #[Test]
    public function maxCompletedTrimming(): void
    {
        $registry = new SpanRegistry(3);

        for ($i = 0; $i < 5; $i++) {
            $span = $this->createSpan('span' . $i);
            $registry->add($span);
            $registry->markCompleted($span);
        }

        $completed = $registry->getCompleted();

        self::assertLessThanOrEqual(3, count($completed));
        // Most recent should be retained
        self::assertArrayHasKey('span4', $completed);
    }

    #[Test]
    public function clear(): void
    {
        $registry = new SpanRegistry();
        $span1 = $this->createSpan('span1');
        $span2 = $this->createSpan('span2');

        $registry->add($span1);
        $registry->add($span2);
        $registry->markCompleted($span1);

        $registry->clear();

        self::assertSame([], $registry->getActive());
        self::assertSame([], $registry->getCompleted());
        self::assertNull($registry->get('span1'));
        self::assertNull($registry->get('span2'));
    }
}
