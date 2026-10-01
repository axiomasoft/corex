<?php

declare(strict_types=1);

use CoreX\Config\Internal\Config;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\TraceContextBridge;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\OutboxRefused;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Observability\Data\OperationContext;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Clock\ClockInterface;

function outboxAppendDeclaredContract(): MessageContract
{
    return new MessageContract(
        name: 'membership.expired',
        version: 1,
        class: MessageClass::IntegrationEvent,
        owner: 'acme/core',
        maxPiiClass: PiiClass::Pseudonymous,
        channel: 'membership',
    );
}

function outboxAppendInstallSchema(): void
{
    Schema::create(Config::outboxMessagesTable(), function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->unsignedSmallInteger('contract_version');
        $table->unsignedSmallInteger('envelope_version');
        $table->string('source');
        $table->string('subject')->nullable();
        $table->string('correlation_id')->nullable();
        $table->string('causation_id')->nullable();
        $table->string('pii_class');
        $table->text('scope');
        $table->text('payload');
        $table->text('metadata');
        $table->dateTimeTz('occurred_at');
        $table->dateTimeTz('available_at');
        $table->string('status');
        $table->unsignedInteger('attempts');
        $table->dateTimeTz('created_at');
        $table->dateTimeTz('updated_at');
    });
}

function outboxAppendBindRegistry(MessageContract ...$contracts): void
{
    app()->instance(
        MessageContractRegistry::class,
        new ArrayMessageContractRegistry(contracts: $contracts),
    );
    app()->forgetScopedInstances();
}

beforeEach(function (): void {
    outboxAppendInstallSchema();
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('refuses append outside a transaction with MSG-008', function (): void {
    $outbox = app(Outbox::class);
    $envelope = messagingSampleEnvelope();

    expect(fn (): mixed => $outbox->append($envelope))
        ->toThrow(OutboxRefused::class, 'MSG-008');
});

it('refuses undeclared contracts inside a transaction with MSG-002', function (): void {
    outboxAppendBindRegistry();
    $outbox = app(Outbox::class);
    $envelope = messagingSampleEnvelope();

    DB::transaction(function () use ($outbox, $envelope): void {
        expect(fn (): mixed => $outbox->append($envelope))
            ->toThrow(UnknownMessageContract::class, 'MSG-002');
    });
});

it('refuses payload personal data above the contract maximum with MSG-007', function (): void {
    $outbox = app(Outbox::class);
    $envelope = messagingSampleEnvelope(['piiClass' => PiiClass::Personal]);

    DB::transaction(function () use ($outbox, $envelope): void {
        expect(fn (): mixed => $outbox->append($envelope))
            ->toThrow(OutboxRefused::class, 'MSG-007');
    });
});

it('persists a pending row with encoded payload and clock-backed timestamps', function (): void {
    $frozen = new DateTimeImmutable('2026-06-15T12:00:00+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
    app()->forgetScopedInstances();

    $payload = new MessagePayload(['kind' => 'expired', 'count' => 2]);
    $envelope = messagingSampleEnvelope(['payload' => $payload]);
    $outbox = app(Outbox::class);

    DB::transaction(function () use ($outbox, $envelope): void {
        $outbox->append($envelope);
    });

    $row = DB::table(Config::outboxMessagesTable())->where('id', $envelope->messageId)->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('pending')
        ->and($row->attempts)->toBe(0)
        ->and($row->payload)->toBe($payload->encode())
        ->and((new DateTimeImmutable((string) $row->available_at))->getTimestamp())->toBe($frozen->getTimestamp())
        ->and((new DateTimeImmutable((string) $row->created_at))->getTimestamp())->toBe($frozen->getTimestamp())
        ->and((new DateTimeImmutable((string) $row->updated_at))->getTimestamp())->toBe($frozen->getTimestamp());
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
        'metadata' => ['trace' => 'keep'],
        'correlationId' => 'corr-keep',
    ]);
    $outbox = app(Outbox::class);

    DB::transaction(function () use ($outbox, $envelope): void {
        $outbox->append($envelope);
    });

    $row = DB::table(Config::outboxMessagesTable())->where('id', 'msg-trace-1')->first();
    $metadata = json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR);

    expect($row->correlation_id)->toBe('corr-keep')
        ->and($row->payload)->toBe($envelope->payload->encode())
        ->and($envelope->metadata)->toBe(['trace' => 'keep'])
        ->and($metadata)->toBe([
            'trace' => 'keep',
            'traceparent' => '00-'.str_repeat('ab', 16).'-'.str_repeat('cd', 8).'-01',
        ]);
});

it('uses one clock instant for available_at created_at and updated_at', function (): void {
    $frozen = new DateTimeImmutable('2024-01-02T03:04:05+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
    app()->forgetScopedInstances();

    $outbox = app(Outbox::class);

    DB::transaction(function () use ($outbox): void {
        $outbox->append(messagingSampleEnvelope(['messageId' => 'msg-clock-1']));
    });

    $row = DB::table(Config::outboxMessagesTable())->where('id', 'msg-clock-1')->first();

    expect($row->available_at)->toBe($row->created_at)
        ->and($row->created_at)->toBe($row->updated_at);
});
