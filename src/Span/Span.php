<?php

declare(strict_types=1);

/**
 * Span
 *
 * Represents a single unit of work within a trace.
 * Contains timing, tags, events, and parent relationship information.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Span;

use BackedEnum;
use DateTimeImmutable;
use PHPdot\TraceLog\Trace\Traceparent;

final class Span
{
    /**
     * @var array<string, string|int|bool>
     */
    private array $tags = [];

    /**
     * @var list<array{name: string, time_ms: float, attributes: array<string, mixed>}>
     */
    private array $events = [];

    private readonly float $startTime;

    private ?float $endTime = null;

    private bool $ended = false;

    private ?BackedEnum $channel;

    /**
     * Create a new Span.
     *
     * @param Traceparent $traceparent This span's traceparent identity
     * @param Traceparent|null $parent Parent span's traceparent
     * @param string $name Span name or operation
     * @param BackedEnum|null $channel Log channel for this span
     */
    public function __construct(
        private readonly Traceparent $traceparent,
        private readonly ?Traceparent $parent,
        private readonly string $name,
        ?BackedEnum $channel = null,
    ) {
        $this->channel   = $channel;
        $this->startTime = microtime(true);
    }

    /**
     * Get this span's traceparent.
     *
     * @return Traceparent The traceparent
     */
    public function getTraceparent(): Traceparent
    {
        return $this->traceparent;
    }

    /**
     * Get parent span's traceparent.
     *
     * @return Traceparent|null Parent traceparent or null for root spans
     */
    public function getParent(): ?Traceparent
    {
        return $this->parent;
    }

    /**
     * Get the span name.
     *
     * @return string Span name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the log channel.
     *
     * @return BackedEnum|null Channel or null if unset
     */
    public function getChannel(): ?BackedEnum
    {
        return $this->channel;
    }

    /**
     * Set the log channel.
     *
     * @param BackedEnum $channel Channel to set
     */
    public function setChannel(BackedEnum $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * Get all tags.
     *
     * @return array<string, string|int|bool> Tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Add a tag to the span.
     *
     * @param string $key Tag key
     * @param string|int|bool $value Tag value
     */
    public function addTag(string $key, string|int|bool $value): void
    {
        $this->tags[$key] = $value;
    }

    /**
     * Get all events.
     *
     * @return list<array{name: string, time_ms: float, attributes: array<string, mixed>}> Events
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Add an event to the span.
     *
     * @param string $name Event name
     * @param array<string, mixed> $attributes Event attributes
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        $this->events[] = [
            'name'       => $name,
            'time_ms'    => round((microtime(true) - $this->startTime) * 1000, 2),
            'attributes' => $attributes,
        ];
    }

    /**
     * Get the start time.
     *
     * @return float Start time as microtime(true)
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * End the span and record the end time.
     */
    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->endTime = microtime(true);
        $this->ended   = true;
    }

    /**
     * Get the end time.
     *
     * @return float|null End time or null if not ended
     */
    public function getEndTime(): ?float
    {
        return $this->endTime;
    }

    /**
     * Get span duration in milliseconds.
     *
     * @return float|null Duration in milliseconds or null if not ended
     */
    public function getDuration(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }

        return ($this->endTime - $this->startTime) * 1000;
    }

    /**
     * Check if span has ended.
     *
     * @return bool True if ended
     */
    public function isEnded(): bool
    {
        return $this->ended;
    }

    /**
     * Convert span to array representation.
     *
     * @return array<string, mixed> Span data
     */
    public function toArray(): array
    {
        return [
            'trace_id'    => $this->traceparent->getTraceId(),
            'span_id'     => $this->traceparent->getSpanId(),
            'parent'      => $this->parent?->toArray(),
            'name'        => $this->name,
            'channel'     => $this->channel?->value,
            'start_time'  => (new DateTimeImmutable('@' . (int) $this->startTime))->format('Y-m-d H:i:s.u'),
            'end_time'    => $this->endTime !== null
                ? (new DateTimeImmutable('@' . (int) $this->endTime))->format('Y-m-d H:i:s.u')
                : null,
            'duration_ms' => $this->getDuration(),
            'tags'        => $this->tags,
            'events'      => $this->events,
        ];
    }
}
