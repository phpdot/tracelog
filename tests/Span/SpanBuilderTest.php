<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Span;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Span\ActiveSpan;
use PHPdot\TraceLog\Span\SpanBuilder;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\TraceLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanBuilderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_builder_' . uniqid();
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
    public function withTagAccumulatesTags(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');
        $result = $builder->withTag('key1', 'value1');

        self::assertSame($builder, $result);

        $builder->withTag('key2', 42);

        $tags = $builder->getTags();
        self::assertSame('value1', $tags['key1']);
        self::assertSame(42, $tags['key2']);
    }

    #[Test]
    public function withChannelOverridesParentChannel(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span', null, Channel::App);

        self::assertSame(Channel::App, $builder->getChannel());

        $result = $builder->withChannel(Channel::Auth);

        self::assertSame($builder, $result);
        self::assertSame(Channel::Auth, $builder->getChannel());
    }

    #[Test]
    public function withTagsAddsMultipleTagsAtOnce(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');
        $result = $builder->withTags(['foo' => 'bar', 'count' => 5]);

        self::assertSame($builder, $result);
        self::assertSame('bar', $builder->getTags()['foo']);
        self::assertSame(5, $builder->getTags()['count']);
    }

    #[Test]
    public function startReturnsActiveSpan(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');
        $active = $builder->start();

        self::assertInstanceOf(ActiveSpan::class, $active);
        self::assertSame('test-span', $active->getSpan()->getName());

        $active->end();
    }

    #[Test]
    public function channelInheritsFromParentWhenNotSet(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span', null, Channel::Database);

        self::assertSame(Channel::Database, $builder->getChannel());
    }

    #[Test]
    public function channelExplicitWithChannelOverridesParent(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span', null, Channel::Database);
        $builder->withChannel(Channel::Security);

        self::assertSame(Channel::Security, $builder->getChannel());
    }

    #[Test]
    public function getNameReturnsSpanName(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'my-operation');

        self::assertSame('my-operation', $builder->getName());
    }

    #[Test]
    public function getParentTraceparentReturnsTraceparent(): void
    {
        $traceLog = $this->createTraceLog();
        $parent = new Traceparent('trace-id', 'span-id');

        $builder = new SpanBuilder($traceLog, 'test-span', $parent);

        self::assertSame($parent, $builder->getParentTraceparent());
    }

    #[Test]
    public function getParentTraceparentReturnsNullWhenNotSet(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');

        self::assertNull($builder->getParentTraceparent());
    }

    #[Test]
    public function getChannelReturnsNullWhenNeitherExplicitNorParent(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');

        self::assertNull($builder->getChannel());
    }

    #[Test]
    public function getTagsReturnsAccumulatedTags(): void
    {
        $traceLog = $this->createTraceLog();

        $builder = new SpanBuilder($traceLog, 'test-span');
        $builder->withTag('a', 'b');
        $builder->withTags(['c' => 'd', 'e' => true]);

        $tags = $builder->getTags();
        self::assertSame('b', $tags['a']);
        self::assertSame('d', $tags['c']);
        self::assertTrue($tags['e']);
    }
}
