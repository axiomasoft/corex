<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;
use CoreX\Messaging\Internal\SqlOutboxRelay;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

require_once dirname(__DIR__, 4).'/tests/Support/WorkScopeFixture.php';
require_once dirname(__DIR__, 4).'/tests/Support/LocalWorkContexts.php';

function messagingWorkScope(): DataScope
{
    if (! app()->bound(WorkScopeFixture::class)) {
        $contexts = LocalWorkContexts::install(app());
        $contexts->initialize(new AccountRef('account-1', 'account-1', 'active', [], []));
    }

    return WorkScopeFixture::install(app())->scope();
}

function messagingInstallTable(string $kind): void
{
    $path = glob(dirname(__DIR__, 2).'/database/migrations/tenant/*_create_sys_'.$kind.'_messages.php');
    (require $path[0])->up();
}

function messagingSampleEnvelope(array $overrides = []): MessageEnvelope
{
    return new MessageEnvelope(
        messageId: $overrides['messageId'] ?? 'msg-1',
        source: $overrides['source'] ?? 'axiomasoft/corex',
        name: $overrides['name'] ?? 'membership.expired',
        contractVersion: $overrides['contractVersion'] ?? 1,
        occurredAt: $overrides['occurredAt'] ?? new DateTimeImmutable('2026-01-01T00:00:00Z'),
        scope: $overrides['scope'] ?? messagingWorkScope(),
        payload: $overrides['payload'] ?? MessagePayload::empty(),
        piiClass: $overrides['piiClass'] ?? PiiClass::None,
        subject: $overrides['subject'] ?? new RecordRef('membership', 'subject-1'),
        correlationId: $overrides['correlationId'] ?? 'corr-1',
        causationId: array_key_exists('causationId', $overrides) ? $overrides['causationId'] : 'cause-1',
        metadata: $overrides['metadata'] ?? [],
        envelopeVersion: $overrides['envelopeVersion'] ?? MessageEnvelope::ENVELOPE_VERSION,
        seal: $overrides['seal'] ?? null,
    );
}

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

function outboxAppendBindRegistry(MessageContract ...$contracts): void
{
    app()->instance(
        MessageContractRegistry::class,
        new ArrayMessageContractRegistry(contracts: $contracts),
    );
    app()->forgetScopedInstances();
}

const OUTBOX_RELAY_OWNER = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

const OUTBOX_RELAY_OTHER_OWNER = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

function outboxRelayMigrate(): void
{
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    test()->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');
    messagingWorkScope();
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
}

function outboxRelayClock(DateTimeImmutable $frozen): void
{
    app()->instance(ClockInterface::class, new FrozenClock($frozen));
}

function outboxRelayService(
    int $leaseSeconds = 120,
    int $maxAttempts = 8,
    int $backoffCapSeconds = 300,
): SqlOutboxRelay {
    return new SqlOutboxRelay(
        current: resolve(CurrentWorkScope::class),
        scopes: resolve(DataScopeCodec::class),
        connection: DB::connection('pgsql'),
        clock: resolve(ClockInterface::class),
        leaseSeconds: $leaseSeconds,
        maxAttempts: $maxAttempts,
        backoffCapSeconds: $backoffCapSeconds,
    );
}

function outboxRelayInsertRow(array $overrides = []): string
{
    $id = $overrides['id'] ?? '01932f5a-0000-7000-8000-000000000001';
    $message = messagingSampleEnvelope(['messageId' => $id, 'payload' => $overrides['payload'] ?? MessagePayload::empty()]);
    DB::transaction(fn () => resolve(Outbox::class)->append(DB::connection(), $message));
    unset($overrides['id'], $overrides['payload']);
    foreach (['scope_raw' => 'scope', 'payload_raw' => 'payload'] as $raw => $column) {
        if (isset($overrides[$raw])) {
            $overrides[$column] = $overrides[$raw];
            unset($overrides[$raw]);
        }
    }

    if ($overrides !== []) {
        DB::table('sys_outbox_messages')->where('message_id', $id)->update($overrides);
    }

    return $id;
}
