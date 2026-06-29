<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Encryption\ChaChaEncryptor;
use PHPdot\TraceLog\TraceLog;
use PHPdot\TraceLog\TraceLogConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the pre-v2 P0 fixes:
 *  - logging can never crash the application (non-UTF-8 context),
 *  - TraceLog::create() actually wires the configured encryption key.
 */
final class P0SecurityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_p0_' . uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    #[Test]
    public function nonUtf8ContextDoesNotCrashAndStillWrites(): void
    {
        $tracelog = TraceLog::create(new TraceLogConfig(logPath: $this->tmpDir));

        // Invalid UTF-8 in context must not throw or fatal the process.
        $tracelog->info('event', ['raw' => "\xB1\x31\xFF binary"]);
        $tracelog->shutdown();

        $files = glob($this->tmpDir . '/*.log');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $content = (string) file_get_contents($files[0]);
        self::assertStringContainsString('event', $content);
    }

    #[Test]
    public function createWiresEncryptionKeyAndSecureEncrypts(): void
    {
        $config = new TraceLogConfig(
            logPath: $this->tmpDir,
            encryptionKey: ChaChaEncryptor::generateKey(),
        );
        $tracelog = TraceLog::create($config);

        $tracelog->info('SSN 123-45-6789 must be encrypted')->secure();
        $tracelog->shutdown();

        $files = glob($this->tmpDir . '/*.log');
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        $content = '';
        foreach ($files as $file) {
            $content .= (string) file_get_contents($file);
        }

        // The configured key is actually used: the SSN never lands in plaintext.
        self::assertStringNotContainsString('123-45-6789', $content);
        self::assertStringContainsString('"encrypted":true', $content);
    }
}
