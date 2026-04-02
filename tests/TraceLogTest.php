<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests;

use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\PendingLog;
use PHPdot\TraceLog\Span\ActiveSpan;
use PHPdot\TraceLog\Span\SpanBuilder;
use PHPdot\TraceLog\Trace\SpanId;
use PHPdot\TraceLog\Trace\TraceId;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceLogTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_main_' . uniqid();
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

    private function createTraceLog(): TraceLog
    {
        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager);

        return new TraceLog($logManager);
    }

    #[Test]
    public function constructorCreatesRootSpan(): void
    {
        $traceLog = $this->createTraceLog();

        $root = $traceLog->rootSpan();

        self::assertInstanceOf(ActiveSpan::class, $root);
        self::assertSame('application', $root->getSpan()->getName());
    }

    #[Test]
    public function spanReturnsSpanBuilder(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = $traceLog->span('child-operation');

        self::assertInstanceOf(SpanBuilder::class, $builder);
    }

    #[Test]
    public function currentSpanReturnsActiveSpan(): void
    {
        $traceLog = $this->createTraceLog();

        $current = $traceLog->currentSpan();

        self::assertInstanceOf(ActiveSpan::class, $current);
    }

    #[Test]
    public function rootSpanReturnsRoot(): void
    {
        $traceLog = $this->createTraceLog();

        $root = $traceLog->rootSpan();
        $current = $traceLog->currentSpan();

        // Initially the root and current should be the same span
        self::assertSame($root->getSpan(), $current->getSpan());
    }

    #[Test]
    public function getTraceparentReturnsTraceparent(): void
    {
        $traceLog = $this->createTraceLog();

        $traceparent = $traceLog->getTraceparent();

        self::assertInstanceOf(Traceparent::class, $traceparent);
    }

    #[Test]
    public function getTraceIdReturnsString(): void
    {
        $traceLog = $this->createTraceLog();

        $traceId = $traceLog->getTraceId();

        self::assertIsString($traceId);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $traceId);
    }

    #[Test]
    public function infoReturnsPendingLog(): void
    {
        $traceLog = $this->createTraceLog();

        $pending = $traceLog->info('Test message');

        self::assertInstanceOf(PendingLog::class, $pending);
    }

    #[Test]
    public function shutdownEndsAllSpans(): void
    {
        $traceLog = $this->createTraceLog();

        $child = $traceLog->span('child')->start();

        $traceLog->shutdown();

        self::assertTrue($child->getSpan()->isEnded());
        self::assertTrue($traceLog->rootSpan()->getSpan()->isEnded());
    }

    #[Test]
    public function fromTraceparentInheritsTrace(): void
    {
        $parentTraceId = TraceId::generate();
        $parentSpanId = SpanId::generate();
        $parent = new Traceparent($parentTraceId->id(), $parentSpanId->id());

        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager);

        $traceLog = TraceLog::fromTraceparent($parent, $logManager);

        self::assertSame($parentTraceId->id(), $traceLog->getTraceId());
    }

    #[Test]
    public function debugReturnsPendingLog(): void
    {
        $traceLog = $this->createTraceLog();

        $pending = $traceLog->debug('Debug message');

        self::assertInstanceOf(PendingLog::class, $pending);
    }

    #[Test]
    public function warningReturnsPendingLog(): void
    {
        $traceLog = $this->createTraceLog();

        $pending = $traceLog->warning('Warning message');

        self::assertInstanceOf(PendingLog::class, $pending);
    }

    #[Test]
    public function errorReturnsPendingLog(): void
    {
        $traceLog = $this->createTraceLog();

        $pending = $traceLog->error('Error message');

        self::assertInstanceOf(PendingLog::class, $pending);
    }

    #[Test]
    public function getContextReturnsTraceContext(): void
    {
        $traceLog = $this->createTraceLog();

        $context = $traceLog->getContext();

        self::assertInstanceOf(\PHPdot\TraceLog\Trace\TraceContext::class, $context);
    }

    #[Test]
    public function getAllSpansReturnsArrayWithRootSpan(): void
    {
        $traceLog = $this->createTraceLog();

        $spans = $traceLog->getAllSpans();

        self::assertNotEmpty($spans);

        $firstSpan = array_values($spans)[0];
        self::assertSame('application', $firstSpan['name']);
    }

    #[Test]
    public function getAllSpansIncludesCompletedSpansAfterChildEnds(): void
    {
        $traceLog = $this->createTraceLog();

        $child = $traceLog->span('child-op')->start();
        $childSpanId = $child->getSpan()->getTraceparent()->getSpanId();
        $child->end();

        $spans = $traceLog->getAllSpans();

        self::assertArrayHasKey($childSpanId, $spans);
        self::assertSame('child-op', $spans[$childSpanId]['name']);
    }

    #[Test]
    public function adoptCreatesSpanBuilderFromExternalTraceparent(): void
    {
        $traceLog = $this->createTraceLog();
        $external = new Traceparent('external-trace-id', 'external-span-id');

        $builder = $traceLog->adopt($external);

        self::assertInstanceOf(SpanBuilder::class, $builder);
        self::assertSame('adopted', $builder->getName());
        self::assertSame($external, $builder->getParentTraceparent());
    }

    #[Test]
    public function createFromTraceLogConfig(): void
    {
        $config = new \PHPdot\TraceLog\TraceLogConfig(
            logPath: $this->tmpDir,
            logLevel: 'debug',
        );

        $traceLog = TraceLog::create($config);

        self::assertInstanceOf(TraceLog::class, $traceLog);
        self::assertNotEmpty($traceLog->getTraceId());
    }

    #[Test]
    public function childSpanLifecycleStartTagInfoEnd(): void
    {
        $traceLog = $this->createTraceLog();

        $child = $traceLog->span('db.query')->start();
        $child->tag('table', 'users');
        $child->info('Query executed');
        $child->end();

        self::assertTrue($child->getSpan()->isEnded());
        self::assertSame('users', $child->getSpan()->getTags()['table']);
        self::assertNotNull($child->getSpan()->getDuration());
    }

    #[Test]
    public function nestedSpansParentChildGrandchild(): void
    {
        $traceLog = $this->createTraceLog();

        $parent = $traceLog->span('parent')->start();
        $child = $parent->span('child')->start();
        $grandchild = $child->span('grandchild')->start();

        $grandchild->end();
        $child->end();
        $parent->end();

        self::assertTrue($grandchild->getSpan()->isEnded());
        self::assertTrue($child->getSpan()->isEnded());
        self::assertTrue($parent->getSpan()->isEnded());
    }

    #[Test]
    public function endSpanEndsChildSpansRecursively(): void
    {
        $traceLog = $this->createTraceLog();

        $parent = $traceLog->span('parent')->start();
        $child = $parent->span('child')->start();

        // End parent -- child should also be ended
        $parent->end();

        self::assertTrue($parent->getSpan()->isEnded());
        self::assertTrue($child->getSpan()->isEnded());
    }

    #[Test]
    public function currentSpanChangesAfterStartingChildSpan(): void
    {
        $traceLog = $this->createTraceLog();

        $rootSpan = $traceLog->currentSpan()->getSpan();
        $child = $traceLog->span('child')->start();
        $currentSpan = $traceLog->currentSpan()->getSpan();

        self::assertNotSame($rootSpan, $currentSpan);
        self::assertSame('child', $currentSpan->getName());

        $child->end();
    }

    #[Test]
    public function currentSpanRevertsAfterChildSpanEnds(): void
    {
        $traceLog = $this->createTraceLog();

        $rootSpan = $traceLog->currentSpan()->getSpan();
        $child = $traceLog->span('child')->start();
        $child->end();

        $currentSpan = $traceLog->currentSpan()->getSpan();

        self::assertSame($rootSpan, $currentSpan);
    }
}
