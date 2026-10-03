<?php

namespace App\Modules\Ai\Application\Agent\Tracing;

use Illuminate\Support\Str;

/**
 * No-op default `SpanRecorder` — same pattern as `NullKafkaProducer`
 * (see `App\Support\NullKafkaProducer` and `AppServiceProvider`). Used in
 * tests and until a persisting implementation is bound; records nothing,
 * but still returns a real span id so callers can build child
 * `TraceContext`s without special-casing "tracing is off".
 */
final class NullSpanRecorder implements SpanRecorder
{
    public function startSpan(TraceContext $context, string $spanType, string $name, array $metadata = []): string
    {
        return Str::uuid()->toString();
    }

    public function endSpan(string $spanId, string $status, array $metadata = []): void
    {
        // Intentionally no-op.
    }
}
