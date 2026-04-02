<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use PHPdot\TraceLog\Trace\SpanId;
use PHPdot\TraceLog\Trace\TraceContext;
use PHPdot\TraceLog\Trace\TraceId;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\Trace\TraceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    #[Test]
    public function constructorCreatesFreshTrace(): void
    {
        $context = TraceContext::create(TraceType::HTTP);

        self::assertInstanceOf(TraceContext::class, $context);
    }

    #[Test]
    public function getTypeReturnsProvidedTraceType(): void
    {
        $context = TraceContext::create(TraceType::QUEUE);

        self::assertSame(TraceType::QUEUE, $context->getType());
    }

    #[Test]
    public function getTraceIdReturnsTraceId(): void
    {
        $context = TraceContext::create(TraceType::HTTP);

        self::assertInstanceOf(TraceId::class, $context->getTraceId());
    }

    #[Test]
    public function getParentTraceparentReturnsNullForFreshTrace(): void
    {
        $context = TraceContext::create(TraceType::HTTP);

        self::assertNull($context->getParentTraceparent());
    }

    #[Test]
    public function fromTraceparentInheritsParentTraceId(): void
    {
        $parentTraceId = TraceId::generate();
        $parentSpanId = SpanId::generate();
        $parent = new Traceparent($parentTraceId->id(), $parentSpanId->id());

        $context = TraceContext::fromTraceparent($parent, TraceType::QUEUE);

        self::assertSame($parentTraceId->id(), $context->getTraceId()->id());
    }

    #[Test]
    public function fromTraceparentStoresParent(): void
    {
        $parentTraceId = TraceId::generate();
        $parentSpanId = SpanId::generate();
        $parent = new Traceparent($parentTraceId->id(), $parentSpanId->id());

        $context = TraceContext::fromTraceparent($parent, TraceType::QUEUE);

        self::assertNotNull($context->getParentTraceparent());
        self::assertSame($parent->getTraceId(), $context->getParentTraceparent()->getTraceId());
        self::assertSame($parent->getSpanId(), $context->getParentTraceparent()->getSpanId());
    }

    #[Test]
    public function fromTraceparentPreservesType(): void
    {
        $parent = new Traceparent(TraceId::generate()->id(), SpanId::generate()->id());

        $context = TraceContext::fromTraceparent($parent, TraceType::CRON);

        self::assertSame(TraceType::CRON, $context->getType());
    }
}
