<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Bridge;

use PHPdot\TraceLog\Bridge\TraceLogBridge;
use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Span\SpanStack;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceLogBridgeTest extends TestCase
{
    #[Test]
    public function returnsTraceFieldsFromCurrentSpan(): void
    {
        $stack = new SpanStack();
        $span = new Span(
            new Traceparent('trace-abc', 'span-xyz'),
            null,
            'test-span',
        );
        $span->addTag('env', 'test');
        $stack->push($span);

        $bridge = new TraceLogBridge($stack);
        $result = $bridge->toArray();

        self::assertSame('trace-abc', $result['trace_id']);
        self::assertSame('span-xyz', $result['span_id']);
        self::assertSame(['env' => 'test'], $result['tags']);
    }

    #[Test]
    public function returnsEmptyArrayWhenNoSpan(): void
    {
        $stack = new SpanStack();
        $bridge = new TraceLogBridge($stack);

        self::assertSame([], $bridge->toArray());
    }

    #[Test]
    public function contextUpdatesWhenSpanChanges(): void
    {
        $stack = new SpanStack();
        $span1 = new Span(
            new Traceparent('trace-abc', 'span-first'),
            null,
            'first-span',
        );
        $stack->push($span1);

        $bridge = new TraceLogBridge($stack);

        self::assertSame('span-first', $bridge->toArray()['span_id']);

        $span2 = new Span(
            new Traceparent('trace-abc', 'span-second'),
            new Traceparent('trace-abc', 'span-first'),
            'second-span',
        );
        $stack->push($span2);

        self::assertSame('span-second', $bridge->toArray()['span_id']);
    }

    #[Test]
    public function contextIncludesCorrectTraceIdFormat(): void
    {
        $stack = new SpanStack();
        $traceId = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';
        $span = new Span(
            new Traceparent($traceId, 'span-id'),
            null,
            'test-span',
        );
        $stack->push($span);

        $bridge = new TraceLogBridge($stack);
        $result = $bridge->toArray();

        self::assertSame($traceId, $result['trace_id']);
    }
}
