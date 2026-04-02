<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use InvalidArgumentException;
use PHPdot\TraceLog\Trace\SpanId;
use PHPdot\TraceLog\Trace\TraceId;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\Trace\TraceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceparentTest extends TestCase
{
    #[Test]
    public function constructorStoresTraceAndSpanIds(): void
    {
        $traceparent = new Traceparent('abc123', 'span456');

        self::assertSame('abc123', $traceparent->getTraceId());
        self::assertSame('span456', $traceparent->getSpanId());
    }

    #[Test]
    public function toHeaderProducesW3cFormat(): void
    {
        $traceId = TraceId::generate(TraceType::HTTP);
        $spanId = SpanId::generate();
        $traceparent = new Traceparent($traceId->id(), $spanId->id());

        $header = $traceparent->toHeader();

        self::assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-zA-Z]{16}-01$/',
            $header,
        );
    }

    #[Test]
    public function fromHeaderParsesValidW3cHeader(): void
    {
        $hex = str_repeat('a', 32);
        $spanHex = str_pad('abc', 16, '0', STR_PAD_LEFT);
        $header = sprintf('00-%s-%s-01', $hex, $spanHex);

        $traceparent = Traceparent::fromHeader($header);

        self::assertNotEmpty($traceparent->getTraceId());
        self::assertNotEmpty($traceparent->getSpanId());
    }

    #[Test]
    public function fromHeaderRoundtrip(): void
    {
        $traceId = TraceId::generate(TraceType::HTTP);
        $spanId = SpanId::generate();
        $original = new Traceparent($traceId->id(), $spanId->id());

        $header = $original->toHeader();
        $restored = Traceparent::fromHeader($header);

        // The trace ID is stored as UUID in fromHeader
        $restoredHex = str_replace('-', '', $restored->getTraceId());
        self::assertSame($traceId->id(), $restoredHex);
    }

    #[Test]
    public function fromArrayAndToArrayRoundtrip(): void
    {
        $original = new Traceparent('trace123', 'span456');
        $array = $original->toArray();
        $restored = Traceparent::fromArray($array);

        self::assertSame($original->getTraceId(), $restored->getTraceId());
        self::assertSame($original->getSpanId(), $restored->getSpanId());
    }

    #[Test]
    public function invalidHeaderThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Traceparent::fromHeader('invalid-header');
    }

    #[Test]
    public function getTraceIdReturnsCorrectValue(): void
    {
        $traceparent = new Traceparent('my-trace-id', 'my-span-id');

        self::assertSame('my-trace-id', $traceparent->getTraceId());
    }

    #[Test]
    public function getSpanIdReturnsCorrectValue(): void
    {
        $traceparent = new Traceparent('my-trace-id', 'my-span-id');

        self::assertSame('my-span-id', $traceparent->getSpanId());
    }

    #[Test]
    public function toStringReturnsHeader(): void
    {
        $traceId = TraceId::generate(TraceType::HTTP);
        $spanId = SpanId::generate();
        $traceparent = new Traceparent($traceId->id(), $spanId->id());

        self::assertSame($traceparent->toHeader(), (string) $traceparent);
    }
}
