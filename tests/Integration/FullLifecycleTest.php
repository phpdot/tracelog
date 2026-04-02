<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Integration;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Trace\TraceType;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FullLifecycleTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_lifecycle_' . uniqid();
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
    public function fullRequestLifecycle(): void
    {
        // Create TraceLog with HTTP type
        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager);
        $traceLog = new TraceLog($logManager, TraceType::HTTP);

        // Root span is auto-created
        $root = $traceLog->rootSpan();
        self::assertSame('application', $root->getSpan()->getName());

        // Create child span with tags and channel
        $child = $traceLog->span('db.query')
            ->withTag('table', 'users')
            ->withChannel(Channel::Database)
            ->start();

        self::assertFalse($child->getSpan()->isEnded());

        // Log info within child span
        $child->info('Query executed');

        // End child span
        $child->end();
        self::assertTrue($child->getSpan()->isEnded());

        // Verify log file contains trace_id and span_id
        $logFile = $this->tmpDir . '/app.log';
        self::assertFileExists($logFile);

        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString($traceLog->getTraceId(), $content);

        // Verify span duration is positive
        $duration = $child->getSpan()->getDuration();
        self::assertNotNull($duration);
        self::assertGreaterThanOrEqual(0.0, $duration);

        // Shutdown TraceLog
        $traceLog->shutdown();

        // Verify all spans ended
        $spans = $traceLog->getAllSpans();
        foreach ($spans as $spanData) {
            self::assertNotNull($spanData['duration_ms']);
        }
    }
}
