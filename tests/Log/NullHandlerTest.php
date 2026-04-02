<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Handler\NullHandler;
use PHPdot\TraceLog\Log\LogLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullHandlerTest extends TestCase
{
    #[Test]
    public function handleDoesNotThrow(): void
    {
        $handler = new NullHandler();

        $handler->handle([
            'level' => LogLevel::ERROR,
            'message' => 'This should be silently discarded',
        ]);

        // No exception = pass
        self::assertTrue(true);
    }

    #[Test]
    public function isHandlingReturnsFalseForAllLevels(): void
    {
        $handler = new NullHandler();

        self::assertFalse($handler->isHandling(LogLevel::DEBUG));
        self::assertFalse($handler->isHandling(LogLevel::INFO));
        self::assertFalse($handler->isHandling(LogLevel::NOTICE));
        self::assertFalse($handler->isHandling(LogLevel::WARNING));
        self::assertFalse($handler->isHandling(LogLevel::ERROR));
        self::assertFalse($handler->isHandling(LogLevel::CRITICAL));
        self::assertFalse($handler->isHandling(LogLevel::ALERT));
        self::assertFalse($handler->isHandling(LogLevel::EMERGENCY));
    }
}
