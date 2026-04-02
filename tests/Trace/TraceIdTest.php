<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use PHPdot\TraceLog\Exception\InvalidIdentifierException;
use PHPdot\TraceLog\Trace\TraceId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceIdTest extends TestCase
{
    #[Test]
    public function generateReturnsValidUuidv7Format(): void
    {
        $id = TraceId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->uuid(),
        );
    }

    #[Test]
    public function generateVersionNibbleIsSeven(): void
    {
        $id = TraceId::generate();
        $hex = $id->id();

        self::assertSame('7', $hex[12]);
    }

    #[Test]
    public function generateVariantBitsAreCorrect(): void
    {
        $id = TraceId::generate();
        $hex = $id->id();

        self::assertContains($hex[16], ['8', '9', 'a', 'b']);
    }

    #[Test]
    public function generateTimestampExtractable(): void
    {
        $before = (int) (microtime(true) * 1000);
        $id = TraceId::generate();
        $after = (int) (microtime(true) * 1000);

        self::assertGreaterThanOrEqual($before, $id->timestamp());
        self::assertLessThanOrEqual($after, $id->timestamp());
    }

    #[Test]
    public function generateUniqueness(): void
    {
        $ids = [];
        for ($i = 0; $i < 10000; $i++) {
            $ids[] = TraceId::generate()->id();
        }

        self::assertCount(10000, array_unique($ids));
    }

    #[Test]
    public function generateSortable(): void
    {
        $first = TraceId::generate();
        usleep(1000);
        $second = TraceId::generate();

        self::assertGreaterThan($first->id(), $second->id());
    }

    #[Test]
    public function fromStringRoundtripUuid(): void
    {
        $original = TraceId::generate();
        $parsed = TraceId::fromString($original->uuid());

        self::assertSame($original->uuid(), $parsed->uuid());
    }

    #[Test]
    public function fromStringRoundtripHex(): void
    {
        $original = TraceId::generate();
        $parsed = TraceId::fromString($original->id());

        self::assertSame($original->id(), $parsed->id());
    }

    #[Test]
    public function fromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        TraceId::fromString('not-a-valid-id');
    }

    #[Test]
    public function idReturns32HexChars(): void
    {
        $id = TraceId::generate();

        self::assertSame(32, strlen($id->id()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id->id());
    }

    #[Test]
    public function uuidReturnsFormattedString(): void
    {
        $id = TraceId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->uuid(),
        );
    }

    #[Test]
    public function timestampReturnsMilliseconds(): void
    {
        $now = (int) (microtime(true) * 1000);
        $id = TraceId::generate();

        self::assertLessThan(100, abs($id->timestamp() - $now));
    }

    #[Test]
    public function toStringReturnsUuid(): void
    {
        $id = TraceId::generate();

        self::assertSame($id->uuid(), (string) $id);
    }

    #[Test]
    public function fromStringAcceptsBothFormats(): void
    {
        $original = TraceId::generate();

        $fromUuid = TraceId::fromString($original->uuid());
        $fromHex = TraceId::fromString($original->id());

        self::assertSame($fromUuid->id(), $fromHex->id());
    }

    #[Test]
    public function fromStringRejectsShortInput(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        TraceId::fromString('abc');
    }

    #[Test]
    public function fromStringRejectsNonHexInput(): void
    {
        $this->expectException(InvalidIdentifierException::class);

        TraceId::fromString(str_repeat('z', 32));
    }
}
