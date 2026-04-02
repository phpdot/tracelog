<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogLevel;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\PendingLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PendingLogTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_pending_' . uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    private function createManager(): LogManager
    {
        $channelManager = new ChannelManager($this->tmpDir, null, LogLevel::DEBUG);

        return new LogManager($channelManager);
    }

    #[Test]
    public function writesOnDestruct(): void
    {
        $manager = $this->createManager();

        $pending = new PendingLog($manager, LogLevel::INFO, 'Destruct message');
        unset($pending);

        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Destruct message', $content);
    }

    #[Test]
    public function secureEncryptsMessage(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);
        $channelManager = new ChannelManager($this->tmpDir, null, LogLevel::DEBUG);
        $manager = new LogManager($channelManager, $encryptor);

        $pending = new PendingLog($manager, LogLevel::INFO, 'Secret data', [], $encryptor);
        $pending->secure();

        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        // The raw message should not appear in the log
        self::assertStringNotContainsString('Secret data', $content);
    }

    #[Test]
    public function doubleWritePrevention(): void
    {
        $manager = $this->createManager();

        $pending = new PendingLog($manager, LogLevel::INFO, 'Only once');
        $pending->secure();
        // Destruct should not write again
        unset($pending);

        $logFile = $this->tmpDir . '/app.log';
        $content = file_get_contents($logFile);
        self::assertIsString($content);

        // Count occurrences of the message — should appear exactly once
        $lines = array_filter(explode("\n", trim($content)));
        self::assertCount(1, $lines);
    }
}
