<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Exception\EncryptionException;
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
        $encryptor = new ChaChaEncryptor(ChaChaEncryptor::generateKey());
        $channelManager = new ChannelManager($this->tmpDir, null, LogLevel::DEBUG);
        $manager = new LogManager($channelManager, $encryptor);

        $pending = new PendingLog($manager, LogLevel::INFO, 'Only once', [], $encryptor);
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

    #[Test]
    public function secureWithoutEncryptorThrowsAndWritesNothing(): void
    {
        $manager = $this->createManager(); // no encryptor configured

        $pending = new PendingLog($manager, LogLevel::INFO, 'SSN 123-45-6789');

        $threw = false;
        try {
            $pending->secure();
        } catch (EncryptionException) {
            $threw = true;
        }
        self::assertTrue($threw, 'secure() must throw when no encryptor is configured');

        // Fail closed: nothing written, and certainly no plaintext — even after destruct.
        unset($pending);

        $logFile = $this->tmpDir . '/app.log';
        $content = is_file($logFile) ? (string) file_get_contents($logFile) : '';
        self::assertStringNotContainsString('123-45-6789', $content);
    }

    #[Test]
    public function secureEncryptsMessageAndContextAndRoundtrips(): void
    {
        $encryptor = new ChaChaEncryptor(ChaChaEncryptor::generateKey());
        $channelManager = new ChannelManager($this->tmpDir, null, LogLevel::DEBUG);
        $manager = new LogManager($channelManager, $encryptor);

        $pending = new PendingLog(
            $manager,
            LogLevel::INFO,
            'Password reset',
            ['email' => 'victim@example.com', 'token' => 'SECRET-TOKEN-123'],
            $encryptor,
        );
        $pending->secure();

        $content = (string) file_get_contents($this->tmpDir . '/app.log');

        // Neither the message nor any context secret appears in plaintext.
        self::assertStringNotContainsString('Password reset', $content);
        self::assertStringNotContainsString('victim@example.com', $content);
        self::assertStringNotContainsString('SECRET-TOKEN-123', $content);

        // The encrypted payload round-trips back to message + context.
        $record = json_decode(trim($content), true);
        self::assertIsArray($record);
        self::assertTrue($record['context']['encrypted'] ?? null);
        self::assertIsString($record['message']);

        $decrypted = json_decode($encryptor->decrypt($record['message']), true);
        self::assertIsArray($decrypted);
        self::assertSame('Password reset', $decrypted['message']);
        self::assertSame('victim@example.com', $decrypted['context']['email']);
        self::assertSame('SECRET-TOKEN-123', $decrypted['context']['token']);
    }
}
