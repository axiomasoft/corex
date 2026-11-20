<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\OutboxRefused;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use CoreX\Observability\Data\OperationContext;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

beforeEach(function (): void {
    messagingWorkScope();
    messagingInstallTable('outbox');
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('resolves the outbox without a key and requires the real secret at protected append', function (): void {
    $envelope = messagingSampleEnvelope();
    config(['app.key' => '']);
    app()->forgetInstance('encrypter');
    app()->forgetScopedInstances();
    $outbox = resolve(Outbox::class);
    expect($outbox)->toBeInstanceOf(Outbox::class)
        ->and(fn () => DB::transaction(fn () => $outbox->append(DB::connection(), $envelope)))->toThrow(MissingAppKeyException::class)
        ->and(DB::table('sys_outbox_messages')->count())->toBe(0);
});

it('refuses append outside a transaction with MSG-008', function (): void {
    $outbox = resolve(Outbox::class);
    $envelope = messagingSampleEnvelope();

    expect(fn (): mixed => $outbox->append(DB::connection(), $envelope))
        ->toThrow(OutboxRefused::class, 'MSG-008');
});

it('refuses undeclared contracts inside a transaction with MSG-002', function (): void {
    outboxAppendBindRegistry();
    $outbox = resolve(Outbox::class);
    $envelope = messagingSampleEnvelope();

    DB::transaction(function () use ($outbox, $envelope): void {
        expect(fn (): mixed => $outbox->append(DB::connection(), $envelope))
            ->toThrow(UnknownMessageContract::class, 'MSG-002');
    });
});

it('refuses payload personal data above the contract maximum with MSG-007', function (): void {
    $outbox = resolve(Outbox::class);
    $envelope = messagingSampleEnvelope(['piiClass' => PiiClass::Personal]);

    DB::transaction(function () use ($outbox, $envelope): void {
        expect(fn (): mixed => $outbox->append(DB::connection(), $envelope))
            ->toThrow(OutboxRefused::class, 'MSG-007');
    });
});

it('persists a pending row with encoded payload and clock-backed timestamps', function (): void {
    $frozen = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
    app()->forgetScopedInstances();

    $payload = new MessagePayload(['kind' => 'expired', 'count' => 2]);
    $envelope = messagingSampleEnvelope(['payload' => $payload]);
    $outbox = resolve(Outbox::class);

    DB::transaction(function () use ($outbox, $envelope): void {
        $outbox->append(DB::connection(), $envelope);
    });

    $row = DB::table('sys_outbox_messages')->where('message_id', $envelope->messageId)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('pending')
        ->and($row->attempts)->toBe(0)
        ->and($row->payload)->toBe($payload->encode())
        ->and(new DateTimeImmutable((string) $row->available_at)->getTimestamp())->toBe($frozen->getTimestamp())
        ->and(new DateTimeImmutable((string) $row->created_at)->getTimestamp())->toBe($frozen->getTimestamp())
        ->and(new DateTimeImmutable((string) $row->updated_at)->getTimestamp())->toBe($frozen->getTimestamp());
});

it('injects trace carrier into persisted metadata without changing identity', function (): void {
    app()->instance(TraceContextBridge::class, new class implements TraceContextBridge
    {
        public function inject(array $metadata): array
        {
            $metadata['traceparent'] = '00-'.str_repeat('ab', 16).'-'.str_repeat('cd', 8).'-01';

            return $metadata;
        }

        public function linkedConsumer(array $metadata, OperationContext $operation, Closure $work): mixed
        {
            return $work();
        }
    });
    app()->forgetScopedInstances();

    $envelope = messagingSampleEnvelope([
        'messageId' => 'msg-trace-1',
        'metadata' => ['tracestate' => 'vendor=keep'],
        'correlationId' => 'corr-keep',
    ]);
    $outbox = resolve(Outbox::class);

    DB::transaction(function () use ($outbox, $envelope): void {
        $outbox->append(DB::connection(), $envelope);
    });

    $row = DB::table('sys_outbox_messages')->where('message_id', 'msg-trace-1')->first();
    $metadata = json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR);

    expect($row->correlation_id)->toBe('corr-keep')
        ->and($row->payload)->toBe($envelope->payload->encode())
        ->and($envelope->metadata)->toBe(['tracestate' => 'vendor=keep'])
        ->and($metadata)->toBe([
            'tracestate' => 'vendor=keep',
            'traceparent' => '00-'.str_repeat('ab', 16).'-'.str_repeat('cd', 8).'-01',
        ]);
});

it('uses one clock instant for available_at created_at and updated_at', function (): void {
    $frozen = new DateTimeImmutable('2024-01-02T03:04:05+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
    app()->forgetScopedInstances();

    $outbox = resolve(Outbox::class);

    DB::transaction(function () use ($outbox): void {
        $outbox->append(DB::connection(), messagingSampleEnvelope(['messageId' => 'msg-clock-1']));
    });

    $row = DB::table('sys_outbox_messages')->where('message_id', 'msg-clock-1')->first();

    expect($row->available_at)->toBe($row->created_at)
        ->and($row->created_at)->toBe($row->updated_at);
});
