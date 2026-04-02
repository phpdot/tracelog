<?php

declare(strict_types=1);

/**
 * TraceLog
 *
 * Main entry point for the TraceLog library.
 * Coordinates distributed tracing with structured logging by managing
 * trace context, span lifecycle, and log enrichment.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog;

use BackedEnum;
use PHPdot\TraceLog\Bridge\TraceLogBridge;
use PHPdot\TraceLog\Log\Channel\Channel;
use PHPdot\TraceLog\Log\Channel\ChannelManager;
use PHPdot\TraceLog\Log\LogLevel;
use PHPdot\TraceLog\Log\LogManager;
use PHPdot\TraceLog\Log\PendingLog;
use PHPdot\TraceLog\Span\ActiveSpan;
use PHPdot\TraceLog\Span\Span;
use PHPdot\TraceLog\Span\SpanBuilder;
use PHPdot\TraceLog\Span\SpanRegistry;
use PHPdot\TraceLog\Span\SpanStack;
use PHPdot\TraceLog\Trace\SpanId;
use PHPdot\TraceLog\Trace\TraceContext;
use PHPdot\TraceLog\Trace\Traceparent;
use PHPdot\TraceLog\Trace\TraceType;
use RuntimeException;

final class TraceLog
{
    private readonly TraceContext $context;
    private readonly LogManager $logManager;
    private readonly SpanStack $spanStack;
    private readonly SpanRegistry $spanRegistry;
    private readonly TraceLogBridge $bridge;
    private readonly BackedEnum $defaultChannel;

    /**
     * Create a new TraceLog instance.
     *
     * Automatically creates a root span and wires the bridge context
     * into the LogManager for log enrichment.
     *
     * @param LogManager $logManager The log manager for writing records
     * @param TraceType $type The trace type
     * @param BackedEnum $defaultChannel Default log channel
     * @param TraceContext|null $context Optional inherited trace context
     * @param int $maxCompleted Maximum completed spans in registry
     */
    public function __construct(
        LogManager $logManager,
        TraceType $type = TraceType::HTTP,
        BackedEnum $defaultChannel = Channel::App,
        ?TraceContext $context = null,
        int $maxCompleted = 100,
    ) {
        $this->context        = $context ?? TraceContext::create($type);
        $this->spanStack      = new SpanStack();
        $this->spanRegistry   = new SpanRegistry($maxCompleted);
        $this->bridge         = new TraceLogBridge($this->spanStack);
        $this->defaultChannel = $defaultChannel;
        $this->logManager     = $logManager->withContext($this->bridge);

        $this->initRootSpan();
    }

    /**
     * Create a TraceLog instance from a propagated traceparent.
     *
     * @param Traceparent $parent The parent traceparent
     * @param LogManager $logManager The log manager
     * @param TraceType $type The trace type for this context
     * @param BackedEnum $defaultChannel Default log channel
     *
     * @return self New TraceLog instance
     */
    public static function fromTraceparent(
        Traceparent $parent,
        LogManager $logManager,
        TraceType $type = TraceType::HTTP,
        BackedEnum $defaultChannel = Channel::App,
    ): self {
        $context = TraceContext::fromTraceparent($parent, $type);

        return new self($logManager, $type, $defaultChannel, $context);
    }

    /**
     * Create a TraceLog instance from a configuration object.
     *
     * @param TraceLogConfig $config The configuration
     *
     * @return self New TraceLog instance
     */
    public static function create(TraceLogConfig $config): self
    {
        $channelManager = new ChannelManager(
            $config->logPath,
            null,
            LogLevel::fromPsr($config->logLevel),
            $config->maxChannels,
        );

        $logManager = new LogManager($channelManager, null, $config->defaultChannel);

        return new self(
            $logManager,
            TraceType::detect(),
            $config->defaultChannel,
            null,
            $config->maxCompletedSpans,
        );
    }

    /**
     * Create a new span builder for a child of the current span.
     *
     * @param string $name Span name
     *
     * @return SpanBuilder Builder for the new span
     */
    public function span(string $name): SpanBuilder
    {
        $current = $this->spanStack->current();

        return new SpanBuilder(
            $this,
            $name,
            $current?->getTraceparent(),
            $current?->getChannel() ?? $this->defaultChannel,
        );
    }

    /**
     * Get the current active span.
     *
     *
     * @throws RuntimeException If no active span
     * @return ActiveSpan Current span wrapper
     */
    public function currentSpan(): ActiveSpan
    {
        $span = $this->spanStack->current();

        if ($span === null) {
            throw new RuntimeException('No active span');
        }

        return new ActiveSpan($span, $this);
    }

    /**
     * Get the root span.
     *
     *
     * @throws RuntimeException If no root span
     * @return ActiveSpan Root span wrapper
     */
    public function rootSpan(): ActiveSpan
    {
        $root = $this->spanStack->root();

        if ($root === null) {
            throw new RuntimeException('No root span');
        }

        return new ActiveSpan($root, $this);
    }

    /**
     * Log a debug message in the current span context.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function debug(string $message, array $context = []): PendingLog
    {
        return $this->logWithCurrentSpan('debug', $message, $context);
    }

    /**
     * Log an info message in the current span context.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function info(string $message, array $context = []): PendingLog
    {
        return $this->logWithCurrentSpan('info', $message, $context);
    }

    /**
     * Log a warning message in the current span context.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function warning(string $message, array $context = []): PendingLog
    {
        return $this->logWithCurrentSpan('warning', $message, $context);
    }

    /**
     * Log an error message in the current span context.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    public function error(string $message, array $context = []): PendingLog
    {
        return $this->logWithCurrentSpan('error', $message, $context);
    }

    /**
     * Get the current span's traceparent for propagation.
     *
     *
     * @throws RuntimeException If no active span
     * @return Traceparent Current traceparent
     */
    public function getTraceparent(): Traceparent
    {
        $current = $this->spanStack->current();

        if ($current === null) {
            throw new RuntimeException('No active span');
        }

        return $current->getTraceparent();
    }

    /**
     * Get the trace ID string.
     *
     * @return string Trace ID
     */
    public function getTraceId(): string
    {
        return $this->context->getTraceId()->id();
    }

    /**
     * Get the trace context.
     *
     * @return TraceContext The trace context
     */
    public function getContext(): TraceContext
    {
        return $this->context;
    }

    /**
     * Get all spans as arrays (active and completed).
     *
     * @return array<string, array<string, mixed>> Span data keyed by span ID
     */
    public function getAllSpans(): array
    {
        $result = [];

        foreach ($this->spanRegistry->getActive() as $id => $span) {
            $result[$id] = $span->toArray();
        }

        foreach ($this->spanRegistry->getCompleted() as $id => $span) {
            $result[$id] = $span->toArray();
        }

        return $result;
    }

    /**
     * Create a child span from an external parent traceparent.
     *
     * @param Traceparent $parent The external parent traceparent
     *
     * @return SpanBuilder Builder for the adopted span
     */
    public function adopt(Traceparent $parent): SpanBuilder
    {
        return new SpanBuilder(
            $this,
            'adopted',
            $parent,
            $this->defaultChannel,
        );
    }

    /**
     * End all active spans in reverse order.
     */
    public function shutdown(): void
    {
        $active = $this->spanRegistry->getActive();

        foreach (array_reverse($active) as $span) {
            $this->endSpan($span);
        }
    }

    /**
     * Start a span and register it in the stack and registry.
     *
     * Called internally by SpanBuilder::start().
     *
     * @param string $name Span name
     * @param Traceparent|null $parent Parent traceparent
     * @param BackedEnum|null $channel Log channel
     * @param array<string, string|int|bool> $tags Initial tags
     *
     * @return ActiveSpan The active span wrapper
     *
     * @internal
     */
    public function startSpan(
        string $name,
        ?Traceparent $parent,
        ?BackedEnum $channel,
        array $tags = [],
    ): ActiveSpan {
        $traceparent = new Traceparent(
            $this->context->getTraceId()->id(),
            SpanId::generate()->id(),
        );

        $span = new Span($traceparent, $parent, $name, $channel);

        foreach ($tags as $key => $value) {
            $span->addTag($key, $value);
        }

        $this->spanRegistry->add($span);
        $this->spanStack->push($span);

        $this->logManager->debug('Span started: ' . $name, [
            'span_id'   => $traceparent->getSpanId(),
            'parent_id' => $parent?->getSpanId(),
        ]);

        return new ActiveSpan($span, $this);
    }

    /**
     * End a span, ending child spans recursively first.
     *
     * Called internally by ActiveSpan::end().
     *
     * @param Span $span The span to end
     *
     * @internal
     */
    public function endSpan(Span $span): void
    {
        if ($span->isEnded()) {
            return;
        }

        $this->endChildSpans($span);
        $span->end();

        $this->spanStack->remove($span);
        $this->spanRegistry->markCompleted($span);

        $this->logManager->debug('Span ended: ' . $span->getName(), [
            'span_id'     => $span->getTraceparent()->getSpanId(),
            'duration_ms' => $span->getDuration(),
            'events'      => $span->getEvents(),
        ]);
    }

    /**
     * Create a pending log entry for a specific span.
     *
     * Called internally by ActiveSpan log methods.
     *
     * @param string $level Log level string
     * @param string $message Log message
     * @param Span $span The span context
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     *
     * @internal
     */
    public function logWithSpan(string $level, string $message, Span $span, array $context = []): PendingLog
    {
        $enriched = array_merge($context, [
            'span_id'  => $span->getTraceparent()->getSpanId(),
            'span_name' => $span->getName(),
        ]);

        return $this->logManager->pending($level, $message, $enriched);
    }

    /**
     * Initialize the root span for this trace.
     */
    private function initRootSpan(): void
    {
        $parentTraceparent = $this->context->getParentTraceparent();

        $traceparent = new Traceparent(
            $this->context->getTraceId()->id(),
            SpanId::generate()->id(),
        );

        $span = new Span(
            $traceparent,
            $parentTraceparent,
            'application',
            $this->defaultChannel,
        );

        $span->addTag('type', 'root');
        $span->addTag('trace_type', $this->context->getType()->name);

        if ($parentTraceparent !== null) {
            $span->addTag('inherited', 'true');
            $span->addTag('parent_span_id', $parentTraceparent->getSpanId());
        }

        $this->spanRegistry->add($span);
        $this->spanStack->push($span);

        $this->logManager->debug('Trace started', [
            'trace_id' => $traceparent->getTraceId(),
            'span_id'  => $traceparent->getSpanId(),
        ]);
    }

    /**
     * End all child spans of a given parent span recursively.
     *
     * @param Span $parent The parent span
     */
    private function endChildSpans(Span $parent): void
    {
        $parentSpanId = $parent->getTraceparent()->getSpanId();

        foreach ($this->spanRegistry->getActive() as $span) {
            $spanParent = $span->getParent();

            if ($spanParent !== null && $spanParent->getSpanId() === $parentSpanId) {
                $this->endSpan($span);
            }
        }
    }

    /**
     * Create a pending log entry using the current span context.
     *
     * @param string $level Log level string
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     *
     * @return PendingLog Pending log entry
     */
    private function logWithCurrentSpan(string $level, string $message, array $context = []): PendingLog
    {
        $span = $this->spanStack->current() ?? $this->spanStack->root();

        if ($span === null) {
            return $this->logManager->pending($level, $message, $context);
        }

        return $this->logWithSpan($level, $message, $span, $context);
    }
}
