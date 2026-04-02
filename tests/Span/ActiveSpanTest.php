<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Span;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\PendingLog;
use PHPdot\TraceLog\Span\ActiveSpan;
use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Span\SpanBuilder;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActiveSpanTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_active_' . uniqid();
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

    private function createActiveSpan(): ActiveSpan
    {
        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager);
        $traceLog = new TraceLog($logManager);
        $span = new Span(new Traceparent('trace1', 'span1'), null, 'test');

        return new ActiveSpan($span, $traceLog);
    }

    #[Test]
    public function tagReturnsSelf(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->tag('key', 'value');

        self::assertSame($active, $result);
    }

    #[Test]
    public function eventReturnsSelf(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->event('something.happened');

        self::assertSame($active, $result);
    }

    #[Test]
    public function tagAddsToUnderlyingSpan(): void
    {
        $active = $this->createActiveSpan();

        $active->tag('env', 'prod');

        self::assertSame('prod', $active->getSpan()->getTags()['env']);
    }

    #[Test]
    public function eventAddsToUnderlyingSpan(): void
    {
        $active = $this->createActiveSpan();

        $active->event('db.query', ['sql' => 'SELECT 1']);

        $events = $active->getSpan()->getEvents();
        self::assertCount(1, $events);
        self::assertSame('db.query', $events[0]['name']);
    }

    #[Test]
    public function channelSetsChannelOnUnderlyingSpanAndReturnsSelf(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->channel(Channel::Auth);

        self::assertSame($active, $result);
        self::assertSame(Channel::Auth, $active->getSpan()->getChannel());
    }

    #[Test]
    public function debugReturnsPendingLog(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->debug('Debug message');

        self::assertInstanceOf(PendingLog::class, $result);
    }

    #[Test]
    public function infoReturnsPendingLog(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->info('Info message');

        self::assertInstanceOf(PendingLog::class, $result);
    }

    #[Test]
    public function warningReturnsPendingLog(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->warning('Warning message');

        self::assertInstanceOf(PendingLog::class, $result);
    }

    #[Test]
    public function errorReturnsPendingLog(): void
    {
        $active = $this->createActiveSpan();

        $result = $active->error('Error message');

        self::assertInstanceOf(PendingLog::class, $result);
    }

    #[Test]
    public function spanReturnsSpanBuilderForChild(): void
    {
        $active = $this->createActiveSpan();

        $builder = $active->span('child-operation');

        self::assertInstanceOf(SpanBuilder::class, $builder);
        self::assertSame('child-operation', $builder->getName());
    }

    #[Test]
    public function endEndsTheUnderlyingSpan(): void
    {
        $channelManager = new ChannelManager($this->tmpDir);
        $logManager = new LogManager($channelManager);
        $traceLog = new TraceLog($logManager);

        $child = $traceLog->span('child')->start();

        self::assertFalse($child->getSpan()->isEnded());
        $child->end();
        self::assertTrue($child->getSpan()->isEnded());
    }

    #[Test]
    public function getSpanReturnsTheSpan(): void
    {
        $active = $this->createActiveSpan();

        self::assertInstanceOf(Span::class, $active->getSpan());
        self::assertSame('test', $active->getSpan()->getName());
    }

    #[Test]
    public function getTraceparentReturnsTraceparent(): void
    {
        $active = $this->createActiveSpan();

        $tp = $active->getTraceparent();

        self::assertInstanceOf(Traceparent::class, $tp);
        self::assertSame('trace1', $tp->getTraceId());
        self::assertSame('span1', $tp->getSpanId());
    }
}
