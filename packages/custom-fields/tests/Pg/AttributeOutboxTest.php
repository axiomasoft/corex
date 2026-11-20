<?php

declare(strict_types=1);

use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\ValueFixture;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('commits one metadata-only sealed v2 fact with its document and replays the original receipt', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'serial-11');
    $patch = $fixture->patch('private serial');
    $token = $patch->token($scope, $record, 'request-1');
    $values = $this->app->make(FieldValues::class);
    $first = $values->update($scope, $record, $patch, $token);
    $again = $values->update($scope, $record, $patch, $token);
    $fact = DB::table('sys_outbox_messages')->sole();
    $receipt = DB::table('sys_attribute_write_tokens')->sole();
    expect($again)->toEqual($first)
        ->and($fact->message_id)->toBe($first->messageId)
        ->and($fact->name)->toBe('fields.values.changed')
        ->and((int) $fact->envelope_version)->toBe(2)
        ->and((int) $fact->contract_version)->toBe(2)
        ->and($fact->scope_seal)->toMatch('/^[a-f0-9]{64}$/')
        ->and(json_decode($fact->payload, true, flags: JSON_THROW_ON_ERROR))->toEqual(['field_ids' => [$fixture->field->id->value],
            'schema_revision' => $fixture->schema->revision->value, 'value_revision' => $first->valueRevision->value])
        ->and(DB::table('sys_attribute_documents')->sole()->document_revision)->toBe($receipt->document_revision)
        ->and($receipt->receipt_id)->toBe($first->receiptId);
});

it('rolls back both the owner mutation and receipt when append fails on the active connection', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope();
    $record = new RecordRef('cas.equipment', 'a');
    $patch = $fixture->patch('value');
    $this->app->instance(Outbox::class, new class implements Outbox
    {
        public function append(ConnectionInterface $connection, MessageEnvelope $message): void
        {
            expect($connection)->toBe(DB::connection())->and($connection->transactionLevel())->toBeGreaterThan(0)
                ->and($connection->table('sys_attribute_documents')->count())->toBe(1)
                ->and($connection->table('sys_attribute_write_tokens')->count())->toBe(1);

            throw new RuntimeException('Simulated append failure.');
        }
    });
    expect(fn () => $this->app->make(FieldValues::class)->update($scope, $record, $patch, $patch->token($scope, $record, 'a')))->toThrow(RuntimeException::class, 'Simulated append failure.')
        ->and(DB::table('sys_attribute_documents')->count())->toBe(0)
        ->and(DB::table('sys_attribute_write_tokens')->count())->toBe(0)
        ->and(DB::table('sys_outbox_messages')->count())->toBe(0);
});
