<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\SynchronousAuditWriter;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Data\Data\JsonDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::connection('testbench')->create('sys_audit_log', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->dateTime('occurred_at');
        $table->string('actor_type');
        $table->string('actor_id')->nullable();
        $table->string('workspace_id')->nullable();
        $table->string('entity_type')->nullable();
        $table->string('entity_id')->nullable();
        $table->string('action');
        $table->text('changes')->nullable();
        $table->text('context');
        $table->dateTime('created_at')->nullable();
    });
});

it('commits a mandatory audit row with the event snapshot despite optional audit being disabled', function (): void {
    config(['corex.audit.enabled' => false, 'queue.default' => 'database']);
    $entry = new AuditEntry(
        action: 'impersonation.started', actorType: ActorType::Support, actorId: 'staff',
        context: new JsonDocument('{"grant":"g1"}'),
        occurredAt: new DateTimeImmutable('2026-10-05T04:00:00+00:00'),
    );

    app(SynchronousAuditWriter::class)->record($entry, 'testbench');

    expect(DB::connection('testbench')->table('sys_audit_log')->count())->toBe(1)
        ->and(DB::connection('testbench')->transactionLevel())->toBe(0);
    $id = DB::connection('testbench')->table('sys_audit_log')->value('id');
    expect(is_string($id) && Str::isUuid($id))->toBeTrue()
        ->and($id[14])->toBe('7');
});

it('rejects an ambient transaction before writing the audit row', function (): void {
    $connection = DB::connection('testbench');
    $connection->beginTransaction();

    try {
        expect(fn () => app(SynchronousAuditWriter::class)->record(new AuditEntry(
            action: 'impersonation.started', actorType: ActorType::Support,
            occurredAt: new DateTimeImmutable('2026-10-05T04:00:00+00:00'),
        ), 'testbench'))->toThrow(RuntimeException::class);
        expect($connection->table('sys_audit_log')->count())->toBe(0);
    } finally {
        $connection->rollBack();
    }
});
