<?php

declare(strict_types=1);

use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Messaging\Internal\DatabasePollPubSub;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql', 'corex.audit.enabled' => true, 'queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
    app()->forgetInstance(CoreOptions::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    LocalWorkContexts::install(app())->initialize(new AccountRef('delivery', 'delivery', 'active', [], []));
    WorkScopeFixture::install(app(), principalId: 'publisher', principalKind: 'service')->scope();
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], DB::getDefaultConnection());
});

it('records committed audit only after the outer transaction succeeds', function (): void {
    $logger = app()->make(QueuedAuditLogger::class);
    foreach ([false, true] as $commit) {
        DB::beginTransaction();
        DB::transaction(fn () => $logger->record(new AuditEntry(action: 'changed', actorType: ActorType::System)));
        expect(DB::table('sys_audit_log')->count())->toBe(0);
        $commit ? DB::commit() : DB::rollBack();
    }
    expect(DB::table('sys_audit_log')->count())->toBe(1);
});

it('serializes publishers so a cursor cannot overtake an uncommitted message', function (): void {
    config(['database.connections.publisher_test' => config('database.connections.pgsql')]);
    $other = DB::connection('publisher_test');
    $firstConnection = DB::connection('pgsql');
    expect($other->scalar('select current_database()'))->toBe($firstConnection->getDatabaseName())
        ->and($other->scalar('select pg_backend_pid()'))->not->toBe($firstConnection->scalar('select pg_backend_pid()'));
    $scope = app(WorkScopeFixture::class)->scope();
    $marker = "select json_build_array(current_database(), inet_server_addr(), inet_server_port(), 'sys_pubsub_messages'::regclass::oid)::text";
    $physical = $firstConnection->scalar($marker, [], false);
    $verifier = app(PhysicalBindingVerifier::class);
    app()->instance(PhysicalBindingVerifier::class, new readonly class($verifier, $other, $scope, $marker, $physical) implements PhysicalBindingVerifier
    {
        public function __construct(private PhysicalBindingVerifier $owner, private ConnectionInterface $second, private DataScope $scope, private string $marker, private string $physical) {}

        public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
        {
            if ($connection !== $this->second) {
                $this->owner->verify($binding, $context, $connection);

                return;
            }
            $binding->assertScope($this->scope);

            if ($context->account->id !== $this->scope->accountId || $context->workspace?->id !== $this->scope->workspaceId
                || $connection->scalar($this->marker, [], false) !== $this->physical) {
                throw new ScopeContextMismatch('The competing publisher is outside the verified message owner.');
            }
        }
    });
    $other->beginTransaction();
    $other->statement("SET LOCAL lock_timeout = '50ms'");
    $first = app()->make(DatabasePollPubSub::class, ['connection' => 'pgsql']);
    $second = app()->make(DatabasePollPubSub::class, ['connection' => 'publisher_test']);
    $publishSecond = fn () => DB::usingConnection('publisher_test', fn () => $second->publish('ordered', ['message' => 'second']));
    DB::beginTransaction();

    try {
        $first->publish('ordered', ['message' => 'first']);
        expect($publishSecond)->toThrow(function (QueryException $error): void {
            expect($error->getCode())->toBe('55P03');
        });
    } finally {
        $firstConnection->commit();
    }
    $publishSecond();
    $other->commit();
    $received = [];
    $first->subscribe('ordered', function (array $payload) use (&$received): void {
        $received[] = $payload['message'];
    }, maxPolls: 1, subscriber: 'regression', replayBacklog: true);
    expect($received)->toBe(['first', 'second']);
    DB::purge('publisher_test');
});

it('isolates duplicate feature definitions without aborting the caller transaction', function (): void {
    $flags = new DatabaseFeatureFlags('pgsql');
    $definition = new FeatureFlagDefinition(key: 'example.toggle', default: false);
    $flags->define($definition);
    DB::table('sys_feature_flags')->where('key', 'example.toggle')->update(['is_enabled' => true]);
    DB::transaction(function () use ($flags, $definition): void {
        $flags->define($definition);
        $flags->define(new FeatureFlagDefinition(key: 'example.other', default: true));
        expect($flags->active('example.toggle'))->toBeTrue();
    });
    expect(DB::table('sys_feature_flags')->count())->toBe(2);
});
