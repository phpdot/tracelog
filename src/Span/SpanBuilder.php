<?php

declare(strict_types=1);

/**
 * Span Builder
 *
 * Builder pattern for creating spans with fluent configuration.
 * Allows setting tags and channel before starting the span.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Span;

use BackedEnum;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\TraceLog;

final class SpanBuilder
{
    /**
     * @var array<string, string|int|bool>
     */
    private array $tags = [];

    private ?BackedEnum $channel = null;

    /**
     * Create a new SpanBuilder.
     *
     * @param TraceLog $traceLog The TraceLog facade
     * @param string $name Span name
     * @param Traceparent|null $parentTraceparent Parent span's traceparent
     * @param BackedEnum|null $parentChannel Parent span's channel for inheritance
     */
    public function __construct(
        private readonly TraceLog $traceLog,
        private readonly string $name,
        private readonly ?Traceparent $parentTraceparent = null,
        private readonly ?BackedEnum $parentChannel = null,
    ) {}

    /**
     * Add a tag to the span.
     *
     * @param string $key Tag key
     * @param string|int|bool $value Tag value
     *
     * @return self For chaining
     */
    public function withTag(string $key, string|int|bool $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    /**
     * Add multiple tags to the span.
     *
     * @param array<string, string|int|bool> $tags Tags to add
     *
     * @return self For chaining
     */
    public function withTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    /**
     * Set the channel for this span.
     *
     * @param BackedEnum $channel The channel
     *
     * @return self For chaining
     */
    public function withChannel(BackedEnum $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Start the span by delegating to TraceLog::startSpan().
     *
     * @return ActiveSpan The active span wrapper
     */
    public function start(): ActiveSpan
    {
        $channel = $this->channel ?? $this->parentChannel;

        return $this->traceLog->startSpan(
            $this->name,
            $this->parentTraceparent,
            $channel,
            $this->tags,
        );
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
     * Get the parent traceparent.
     *
     * @return Traceparent|null Parent traceparent
     */
    public function getParentTraceparent(): ?Traceparent
    {
        return $this->parentTraceparent;
    }

    /**
     * Get the resolved channel (explicit or inherited from parent).
     *
     * @return BackedEnum|null The channel
     */
    public function getChannel(): ?BackedEnum
    {
        return $this->channel ?? $this->parentChannel;
    }

    /**
     * Get the tags.
     *
     * @return array<string, string|int|bool> Tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
