<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use PHPdot\TraceLog\Exception\InvalidIdentifierException;
use PHPdot\TraceLog\Trace\SpanId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanIdTest extends TestCase
{
    #[Test]
    public function generateReturns16HexChars(): void
    {
        $id = SpanId::generate();

        self::assertSame(16, strlen($id->id()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id->id());
    }

    #[Test]
    public function generateTimestampInFirst12Chars(): void
    {
        $before = (int) (microtime(true) * 1000);
        $id = SpanId::generate();
        $after = (int) (microtime(true) * 1000);

        self::assertGreaterThanOrEqual($before, $id->getTimestamp());
        self::assertLessThanOrEqual($after, $id->getTimestamp());
    }

    #[Test]
    public function generateUniqueness(): void
    {
        $ids = [];
        for ($i = 0; $i < 10000; $i++) {
            $ids[] = SpanId::generate()->id();
        }

        self::assertCount(10000, array_unique($ids));
    }

    #[Test]
    public function generateSortable(): void
    {
        $first = SpanId::generate();
        usleep(10000);
        $second = SpanId::generate();

        self::assertGreaterThan($first->id(), $second->id());
    }

    #[Test]
    public function fromStringRoundtrip(): void
    {
        $original = SpanId::generate();
        $parsed = SpanId::fromString($original->id());

        self::assertSame($original->id(), $parsed->id());
    }

    #[Test]
    public function fromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        SpanId::fromString('not-valid-hex');
    }

    #[Test]
    public function fromStringRejectsWrongLength(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        SpanId::fromString('abcdef');
    }

    #[Test]
    public function fromStringAcceptsUppercase(): void
    {
        $original = SpanId::generate();
        $upper = strtoupper($original->id());
        $parsed = SpanId::fromString($upper);

        self::assertSame($original->id(), $parsed->id());
    }

    #[Test]
    public function timestampExtraction(): void
    {
        $now = (int) (microtime(true) * 1000);
        $id = SpanId::generate();

        self::assertLessThan(100, abs($id->getTimestamp() - $now));
    }

    #[Test]
    public function toStringReturnsHex(): void
    {
        $id = SpanId::generate();

        self::assertSame($id->id(), (string) $id);
    }

    #[Test]
    public function w3cCompatible(): void
    {
        $id = SpanId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id->id());
    }

    #[Test]
    public function monotonicWithinSameMillisecond(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = SpanId::generate()->id();
        }

        $sorted = $ids;
        sort($sorted);

        self::assertSame($sorted, $ids);
    }

    #[Test]
    public function handlesHighVolumeWithoutCollision(): void
    {
        $ids = [];
        for ($i = 0; $i < 50000; $i++) {
            $ids[] = SpanId::generate()->id();
        }

        self::assertCount(50000, array_unique($ids));
    }
}
