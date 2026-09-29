<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Internal\NullTraceContextBridge;
use CoreX\Observability\Contracts\OperationObserver;
use CoreX\Observability\Enums\OperationOutcome;
use CoreX\Observability\Internal\MetricAttributes;
use CoreX\Observability\Internal\NullOperationObserver;
use CoreX\Observability\Internal\OpenTelemetryBridge;
use CoreX\Observability\Internal\OperationContext;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

it('accepts the five metric keys and four retry buckets', function (): void {
    $attributes = new MetricAttributes([
        'package' => 'core.messaging',
        'operation' => 'outbox.publish',
        'outcome' => 'succeeded',
        'category' => 'unavailable',
        'retry_bucket' => MetricAttributes::retryBucket(attempt: 3),
    ]);

    expect($attributes->all())->toBe([
        'package' => 'core.messaging',
        'operation' => 'outbox.publish',
        'outcome' => 'succeeded',
        'category' => 'unavailable',
        'retry_bucket' => '2-3',
    ])
        ->and(MetricAttributes::retryBucket(attempt: 0))->toBe('0')
        ->and(MetricAttributes::retryBucket(attempt: 1))->toBe('1')
        ->and(MetricAttributes::retryBucket(attempt: 4))->toBe('4+')
        ->and(OperationOutcome::cases())->toHaveCount(3);
});

it('rejects forbidden and unbounded metric labels before any exporter call', function (): void {
    expect(fn () => new MetricAttributes(['account_id' => 'account-1']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MetricAttributes(['contract' => 'membership.expired']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MetricAttributes(['operation_id' => 'op-1']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MetricAttributes(['package' => '01932f5a-0000-7000-8000-000000000099']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MetricAttributes(['retry_bucket' => '9']))->toThrow(InvalidArgumentException::class);
});

it('runs consumer work once on the null bridge and on adapter fallback', function (): void {
    $context = new OperationContext(
        operationId: 'op-1',
        correlationId: 'corr-1',
        causationId: null,
        accountId: null,
        workspaceId: null,
        actorType: ActorType::System,
        actorId: null,
        package: 'core.messaging',
        operation: 'outbox.consume',
        attempt: 0,
    );
    $calls = 0;
    $work = function () use (&$calls): string {
        $calls++;

        return 'done';
    };

    expect((new NullTraceContextBridge)->linkedConsumer(metadata: [], operation: $context, work: $work))->toBe('done')
        ->and($calls)->toBe(1);

    expect((new OpenTelemetryBridge)->linkedConsumer(metadata: ['correlation_id' => 'corr-1'], operation: $context, work: $work))->toBe('done')
        ->and($calls)->toBe(2);

    expect(fn () => (new NullTraceContextBridge)->linkedConsumer(
        metadata: [],
        operation: $context,
        work: function (): never {
            throw new RuntimeException('business');
        },
    ))->toThrow(RuntimeException::class, 'business');
});

it('links the consumer span to the producer trace and exports five health instruments', function (): void {
    if (! class_exists(InMemoryExporter::class)) {
        test()->skip('OpenTelemetry SDK is not installed.');
    }

    $exporter = new InMemoryExporter;
    $provider = new TracerProvider(new SimpleSpanProcessor($exporter));
    $tracer = $provider->getTracer('corex', schemaUrl: OpenTelemetryBridge::SEMCONV_SCHEMA_URL);
    $meter = new RecordingMeter;
    $bridge = new OpenTelemetryBridge(tracer: $tracer, meter: $meter);
    $context = new OperationContext(
        operationId: 'op-1',
        correlationId: 'not-a-trace-id',
        causationId: null,
        accountId: 'account-1',
        workspaceId: null,
        actorType: ActorType::System,
        actorId: null,
        package: 'core.messaging',
        operation: 'outbox.consume',
        attempt: 1,
    );

    $producer = $tracer->spanBuilder('produce')->startSpan();
    $scope = $producer->activate();
    $metadata = $bridge->inject(metadata: []);
    $scope->detach();
    $producer->end();

    $bridge->linkedConsumer(metadata: $metadata, operation: $context, work: fn (): string => 'consumed');
    $provider->forceFlush();

    $spans = $exporter->getSpans();
    $consumer = $spans[array_key_last($spans)];
    $links = $consumer->getLinks();

    $bridge->exportDeliveryHealth(
        oldestPendingSeconds: 4,
        pending: 3,
        publishing: 1,
        dead: 0,
        maxAttempts: 2,
    );

    expect($metadata)->toHaveKey('traceparent')
        ->and($links)->toHaveCount(1)
        ->and($links[0]->getSpanContext()->getTraceId())->toBe($producer->getContext()->getTraceId())
        ->and($consumer->getContext()->getTraceId())->not->toBe('not-a-trace-id')
        ->and(array_column($meter->gauges, 0))->toBe([
            'corex.messaging.outbox.oldest_pending.seconds',
            'corex.messaging.outbox.pending',
            'corex.messaging.outbox.publishing',
            'corex.messaging.outbox.dead',
            'corex.messaging.outbox.max_attempts',
        ])
        ->and($meter->gauges[0][2])->toBe([]);
});

it('boots null observer and trace bridge without an OpenTelemetry binding', function (): void {
    expect(app(OperationObserver::class))->toBeInstanceOf(NullOperationObserver::class)
        ->and(app(TraceContextBridge::class))->toBeInstanceOf(NullTraceContextBridge::class)
        ->and(class_exists(OpenTelemetryBridge::class))->toBeTrue();
});

final class RecordingMeter
{
    /** @var list<array{0: string, 1: int|float, 2: array<mixed>}> */
    public array $gauges = [];

    /**
     * @param  array<string, mixed>  $advisory
     */
    public function createGauge(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): object
    {
        return new class($name, $this)
        {
            public function __construct(
                private readonly string $name,
                private readonly RecordingMeter $meter,
            ) {}

            /**
             * @param  iterable<mixed>  $attributes
             */
            public function record(int|float $amount, iterable $attributes = []): void
            {
                $this->meter->gauges[] = [$this->name, $amount, iterator_to_array($attributes)];
            }
        };
    }
}
