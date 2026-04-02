<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Trace;

use PHPdot\TraceLog\Trace\TraceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceTypeTest extends TestCase
{
    #[Test]
    public function allCasesHaveUniqueValues(): void
    {
        $values = array_map(
            static fn(TraceType $case): int => $case->value,
            TraceType::cases(),
        );

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function allValuesFitInThreeBits(): void
    {
        foreach (TraceType::cases() as $case) {
            self::assertGreaterThanOrEqual(0, $case->value);
            self::assertLessThanOrEqual(7, $case->value);
        }
    }

    #[Test]
    public function detectReturnsCliInCliSapi(): void
    {
        self::assertSame(TraceType::CLI, TraceType::detect());
    }

    #[Test]
    public function fromRoundtripForAllCases(): void
    {
        foreach (TraceType::cases() as $case) {
            $restored = TraceType::from($case->value);
            self::assertSame($case, $restored);
        }
    }
}
