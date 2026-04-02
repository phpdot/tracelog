<?php

declare(strict_types=1);

/**
 * Trace Context
 *
 * Request-level context that holds the trace identifier, span identifier,
 * and trace type. Supports both new traces and inherited traces from
 * parent contexts for distributed tracing propagation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Trace;

final class TraceContext
{
    private readonly TraceId $traceId;
    private readonly SpanId $spanId;

    /**
     * Create a new TraceContext.
     *
     * @param TraceType $type The trace type
     * @param Traceparent|null $parentTraceparent The parent traceparent for inherited contexts
     */
    private function __construct(
        private readonly TraceType $type,
        private readonly ?Traceparent $parentTraceparent = null,
    ) {
        if ($this->parentTraceparent !== null) {
            $this->traceId = TraceId::fromString($this->parentTraceparent->getTraceId());
        } else {
            $this->traceId = TraceId::generate();
        }

        $this->spanId = SpanId::generate();
    }

    /**
     * Create a fresh trace context with no parent.
     *
     * @param TraceType $type The trace type
     *
     * @return self New TraceContext instance
     */
    public static function create(TraceType $type): self
    {
        return new self($type);
    }

    /**
     * Create a context inherited from a parent traceparent.
     *
     * @param Traceparent $parent The parent traceparent
     * @param TraceType $type The trace type for this context
     *
     * @return self New TraceContext instance
     */
    public static function fromTraceparent(Traceparent $parent, TraceType $type): self
    {
        return new self($type, $parent);
    }

    /**
     * Get the trace identifier.
     *
     * @return TraceId The trace ID object
     */
    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    /**
     * Get the span identifier.
     *
     * @return SpanId The span ID object
     */
    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    /**
     * Get the trace type.
     *
     * @return TraceType The trace type
     */
    public function getType(): TraceType
    {
        return $this->type;
    }

    /**
     * Get the traceparent for this context.
     *
     * @return Traceparent The traceparent containing this context's trace and span IDs
     */
    public function getTraceparent(): Traceparent
    {
        return new Traceparent($this->traceId->id(), $this->spanId->id());
    }

    /**
     * Get the parent traceparent if this context was inherited.
     *
     * @return Traceparent|null The parent traceparent or null for root contexts
     */
    public function getParentTraceparent(): ?Traceparent
    {
        return $this->parentTraceparent;
    }
}
