<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Integration;

use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\Trace\TraceType;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TracePropagationTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_propagation_' . uniqid();
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
    public function tracePropagationAcrossServices(): void
    {
        // Create TraceLog A (HTTP)
        $channelManagerA = new ChannelManager($this->tmpDir);
        $logManagerA = new LogManager($channelManagerA);
        $traceLogA = new TraceLog($logManagerA, TraceType::HTTP);

        // Get traceparent from A
        $traceparentA = $traceLogA->getTraceparent();

        // Serialize to header string
        $headerString = $traceparentA->toHeader();
        self::assertNotEmpty($headerString);
        self::assertStringStartsWith('00-', $headerString);

        // Parse header string into Traceparent
        $parsedTraceparent = Traceparent::fromHeader($headerString);

        // Create TraceLog B from traceparent (QUEUE type)
        $channelManagerB = new ChannelManager($this->tmpDir);
        $logManagerB = new LogManager($channelManagerB);
        $traceLogB = TraceLog::fromTraceparent($parsedTraceparent, $logManagerB, TraceType::QUEUE);

        // Verify B has same trace_id as A
        self::assertSame($traceLogA->getTraceId(), $traceLogB->getTraceId());

        // Verify B has different span_id
        $spanIdA = $traceLogA->getTraceparent()->getSpanId();
        $spanIdB = $traceLogB->getTraceparent()->getSpanId();
        self::assertNotSame($spanIdA, $spanIdB);

        // Verify B's type is QUEUE
        self::assertSame(TraceType::QUEUE, $traceLogB->getContext()->getType());

        $traceLogA->shutdown();
        $traceLogB->shutdown();
    }
}
