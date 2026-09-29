<?php

declare(strict_types=1);

namespace CoreX\Observability\Internal;

use Closure;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

/**
 * Optional OpenTelemetry adapter. SDK type names stay in this class.
 *
 * Messaging Development semantic conventions are pinned; an SDK upgrade does
 * not change the schema URL by itself.
 *
 * Account, workspace, actor and operation ids are not metric labels.
 */
final class OpenTelemetryBridge
{
    public const SEMCONV_SCHEMA_URL = 'https://opentelemetry.io/schemas/1.32.0';

    public function __construct(
        private readonly ?object $tracer = null,
        private readonly ?object $meter = null,
    ) {}

    /**
     * @param  array<string, scalar|null>  $metadata
     * @return array<string, scalar|null>
     */
    public function inject(array $metadata): array
    {
        if ($this->tracer === null || ! class_exists(TraceContextPropagator::class)) {
            return $metadata;
        }

        try {
            $carrier = [];
            TraceContextPropagator::getInstance()->inject($carrier);
            foreach (['traceparent', 'tracestate'] as $key) {
                $value = $carrier[$key] ?? null;

                if (is_string($value) && self::carrierValue(key: $key, value: $value)) {
                    $metadata[$key] = $value;
                }
            }
        } catch (Throwable) {
            return $metadata;
        }

        return $metadata;
    }

    /**
     * @template T
     *
     * @param  array<string, scalar|null>  $metadata
     * @param  Closure(): T  $work
     * @return T
     */
    public function linkedConsumer(array $metadata, OperationContext $operation, Closure $work): mixed
    {
        if ($this->tracer === null) {
            return $work();
        }

        $scope = null;
        $span = null;

        try {
            $span = $this->consumerSpan(metadata: $metadata, operation: $operation);

            if (is_object($span) && method_exists($span, 'activate')) {
                $scope = $span->activate();
            }
        } catch (Throwable) {
            $span = null;
            $scope = null;
        }

        try {
            return $work();
        } finally {
            if (is_object($scope) && method_exists($scope, 'detach')) {
                $scope->detach();
            }

            if (is_object($span) && method_exists($span, 'end')) {
                $span->end();
            }
        }
    }

    /**
     * Aggregate delivery gauges only. The per-contract map is not a label.
     */
    public function exportDeliveryHealth(
        ?int $oldestPendingSeconds,
        int $pending,
        int $publishing,
        int $dead,
        int $maxAttempts,
    ): void {
        if ($this->meter === null) {
            return;
        }

        $readings = [
            'corex.messaging.outbox.oldest_pending.seconds' => $oldestPendingSeconds ?? 0,
            'corex.messaging.outbox.pending' => $pending,
            'corex.messaging.outbox.publishing' => $publishing,
            'corex.messaging.outbox.dead' => $dead,
            'corex.messaging.outbox.max_attempts' => $maxAttempts,
        ];

        foreach ($readings as $name => $value) {
            $gauge = $this->call($this->meter, 'createGauge', $name, null, null, ['schema_url' => self::SEMCONV_SCHEMA_URL]);

            if (is_object($gauge)) {
                $this->call($gauge, 'record', $value);
            }
        }
    }

    public function record(MetricAttributes $attributes): void
    {
        if ($this->meter === null) {
            return;
        }

        $counter = $this->call($this->meter, 'createCounter', 'corex.operation', null, null, ['schema_url' => self::SEMCONV_SCHEMA_URL]);

        if (is_object($counter)) {
            $this->call($counter, 'add', 1, $attributes->all());
        }
    }

    private function call(object $target, string $method, mixed ...$arguments): mixed
    {
        if (! method_exists($target, $method)) {
            return null;
        }

        return $target->{$method}(...$arguments);
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     */
    private function consumerSpan(array $metadata, OperationContext $operation): ?object
    {
        if (! is_object($this->tracer)) {
            return null;
        }

        $builder = $this->call($this->tracer, 'spanBuilder', $operation->operation);

        if (! is_object($builder)) {
            return null;
        }
        $carrier = [];
        foreach (['traceparent', 'tracestate'] as $key) {
            $value = $metadata[$key] ?? null;

            if (is_string($value) && self::carrierValue(key: $key, value: $value)) {
                $carrier[$key] = $value;
            }
        }

        if ($carrier !== [] && class_exists(TraceContextPropagator::class)) {
            $extracted = TraceContextPropagator::getInstance()->extract($carrier);
            $remote = Span::fromContext($extracted)->getContext();

            if ($remote->isValid()) {
                $linked = $this->call($builder, 'addLink', $remote);

                if (is_object($linked)) {
                    $builder = $linked;
                }
            }
        }

        $kinded = $this->call($builder, 'setSpanKind', SpanKind::KIND_CONSUMER);

        if (is_object($kinded)) {
            $builder = $kinded;
        }

        $span = $this->call($builder, 'startSpan');

        return is_object($span) ? $span : null;
    }

    private static function carrierValue(string $key, string $value): bool
    {
        if ($key === 'traceparent') {
            return preg_match('/\\A[0-9a-f]{2}-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}\\z/', $value) === 1;
        }

        return $key === 'tracestate' && $value !== '' && strlen($value) <= 512 && preg_match('/[\\x00-\\x1f\\x7f]/', $value) !== 1;
    }
}
