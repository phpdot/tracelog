<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Integration;

use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncryptedLoggingTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_encrypted_' . uniqid();
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

    #[Test]
    public function encryptedLoggingRoundtrip(): void
    {
        $key = ChaChaEncryptor::generateKey();
        $encryptor = new ChaChaEncryptor($key);

        // Create LogManager with ChaChaEncryptor
        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager, $encryptor);

        // Create TraceLog
        $traceLog = new TraceLog($logManager);

        // Log a secure message
        $originalMessage = 'Sensitive credit card: 4111-1111-1111-1111';
        $traceLog->info($originalMessage)->secure();

        // Read log file
        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);

        // Verify message field is encrypted (not plaintext)
        self::assertStringNotContainsString($originalMessage, $content);

        // Find the encrypted message line (the secure() call writes it)
        $lines = array_filter(explode("\n", $content));
        $encryptedLine = null;
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['context']['encrypted']) && $decoded['context']['encrypted'] === true) {
                $encryptedLine = $decoded;
                break;
            }
        }

        self::assertNotNull($encryptedLine, 'Expected to find an encrypted log entry');
        self::assertIsString($encryptedLine['message']);

        // Decrypt the message field — secure() now encrypts a {message, context} envelope so the
        // user's context (where the real secrets usually live) is protected too, not just the message.
        $payload = json_decode($encryptor->decrypt($encryptedLine['message']), true);
        self::assertIsArray($payload);

        // Verify original message recovered from the encrypted envelope
        self::assertSame($originalMessage, $payload['message']);

        $traceLog->shutdown();
    }
}
