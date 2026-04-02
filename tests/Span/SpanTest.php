<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Span;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    private function createSpan(string $name = 'test-span', ?Traceparent $parent = null): Span
    {
        $traceparent = new Traceparent('abc123', 'span456');

        return new Span($traceparent, $parent, $name, Channel::App);
    }

    #[Test]
    public function constructorStoresProperties(): void
    {
        $traceparent = new Traceparent('trace1', 'span1');
        $parent = new Traceparent('trace1', 'span0');
        $span = new Span($traceparent, $parent, 'my-operation', Channel::Http);

        self::assertSame($traceparent, $span->getTraceparent());
        self::assertSame($parent, $span->getParent());
        self::assertSame('my-operation', $span->getName());
        self::assertSame(Channel::Http, $span->getChannel());
    }

    #[Test]
    public function addTagStoresTags(): void
    {
        $span = $this->createSpan();

        $span->addTag('http.method', 'GET');
        $span->addTag('http.status', 200);
        $span->addTag('cached', true);

        $tags = $span->getTags();

        self::assertSame('GET', $tags['http.method']);
        self::assertSame(200, $tags['http.status']);
        self::assertTrue($tags['cached']);
    }

    #[Test]
    public function addEventStoresEventsWithTimestamp(): void
    {
        $span = $this->createSpan();

        $span->addEvent('query.start', ['sql' => 'SELECT 1']);

        $events = $span->getEvents();

        self::assertCount(1, $events);
        self::assertSame('query.start', $events[0]['name']);
        self::assertArrayHasKey('time_ms', $events[0]);
        self::assertIsFloat($events[0]['time_ms']);
        self::assertSame(['sql' => 'SELECT 1'], $events[0]['attributes']);
    }

    #[Test]
    public function endSetsEndTime(): void
    {
        $span = $this->createSpan();

        self::assertNull($span->getEndTime());

        $span->end();

        self::assertNotNull($span->getEndTime());
    }

    #[Test]
    public function getDurationReturnsMilliseconds(): void
    {
        $span = $this->createSpan();

        self::assertNull($span->getDuration());

        usleep(1000); // 1ms
        $span->end();

        $duration = $span->getDuration();
        self::assertNotNull($duration);
        self::assertGreaterThan(0.0, $duration);
    }

    #[Test]
    public function isEndedReflectsState(): void
    {
        $span = $this->createSpan();

        self::assertFalse($span->isEnded());

        $span->end();

        self::assertTrue($span->isEnded());
    }

    #[Test]
    public function endIsIdempotent(): void
    {
        $span = $this->createSpan();

        $span->end();
        $firstEnd = $span->getEndTime();

        usleep(1000);
        $span->end();
        $secondEnd = $span->getEndTime();

        self::assertSame($firstEnd, $secondEnd);
    }

    #[Test]
    public function toArraySerializesAllData(): void
    {
        $span = $this->createSpan();
        $span->addTag('env', 'test');
        $span->addEvent('start', ['key' => 'val']);
        $span->end();

        $array = $span->toArray();

        self::assertArrayHasKey('trace_id', $array);
        self::assertArrayHasKey('span_id', $array);
        self::assertArrayHasKey('parent', $array);
        self::assertArrayHasKey('name', $array);
        self::assertArrayHasKey('channel', $array);
        self::assertArrayHasKey('start_time', $array);
        self::assertArrayHasKey('end_time', $array);
        self::assertArrayHasKey('duration_ms', $array);
        self::assertArrayHasKey('tags', $array);
        self::assertArrayHasKey('events', $array);
        self::assertSame('test-span', $array['name']);
        self::assertSame(['env' => 'test'], $array['tags']);
        self::assertCount(1, $array['events']);
    }
}
