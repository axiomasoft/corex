<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/Messaging.php';

use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Exceptions\OutboxRefused;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');

    messagingWorkScope();
    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('rolls back an outbox row with the business transaction', function (): void {
    $outbox = resolve(Outbox::class);

    try {
        DB::connection('pgsql')->transaction(function () use ($outbox): void {
            $outbox->append(DB::connection(), messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000099']));

            throw new RuntimeException('force-rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force-rollback');
    }

    expect(DB::connection('pgsql')->table('sys_outbox_messages')->count())->toBe(0);
})->group('pg');

it('addresses immutable replay by logical scope and source and rolls a changed digest back', function (): void {
    $db = DB::connection();
    $id = '01932f5a-0000-7000-8000-000000000171';
    $message = messagingSampleEnvelope(['messageId' => $id]);
    $append = resolve(Outbox::class);
    $db->transaction(function () use ($db, $append, $message, $id): void {
        $append->append($db, $message);
        $append->append($db, $message);
        $append->append($db, messagingSampleEnvelope(['messageId' => $id, 'source' => 'urn:other']));
    });
    $conflict = $message->withPayload(new MessagePayload(['different' => true]));
    expect(fn () => $db->transaction(fn () => $append->append($db, $conflict)))->toThrow(OutboxRefused::class)
        ->and($db->table('sys_outbox_messages')->count())->toBe(2);
    config(['database.connections.another_writer' => config('database.connections.pgsql')]);
    $other = DB::connection('another_writer');
    expect(fn () => $other->transaction(fn () => $append->append($other, $message)))->toThrow(ScopeContextMismatch::class);
})->group('pg');

it('enforces the registered lineage on derived facts and accepts an opaque producer ID', function (): void {
    $sample = outboxAppendDeclaredContract();
    $derived = new MessageContract($sample->name, $sample->version, $sample->class, $sample->owner, $sample->maxPiiClass, $sample->channel,
        dataSchema: 'urn:acme:schema:membership.expired.v1', derived: true, handler: 'membership-consumer');
    outboxAppendBindRegistry($derived);
    $db = DB::connection();
    $append = resolve(Outbox::class);
    $message = messagingSampleEnvelope(['messageId' => 'opaque-order-4', 'causationId' => null]);
    expect(fn () => $db->transaction(fn () => $append->append($db, $message)))->toThrow(InvalidMessage::class);
    $db->transaction(fn () => $append->append($db, messagingSampleEnvelope(['messageId' => 'opaque-order-4', 'causationId' => 'fact-before-expiry'])));
    expect($db->table('sys_outbox_messages')->value('message_id'))->toBe('opaque-order-4')
        ->and($derived->handler)->toBe('membership-consumer');
})->group('pg');
