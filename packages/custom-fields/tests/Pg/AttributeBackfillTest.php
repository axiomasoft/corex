<?php

declare(strict_types=1);

use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Internal\SchemaConflict;
use CoreX\CustomFields\Schemas\Internal\SchemaRows;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Data\RecordRef;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

uses(SchemaPgTestCase::class)->group('pg');

it('blocks switch on a failed historical conversion and clears only a corrected source revision', function (): void {
    $field = $this->field('code', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $doc = [...SchemaRows::logical($this->scope), 'entity' => $this->entity->value, 'record_id' => 'r1'];
    $original = $this->writeValue($field, 'r1', 'a');
    $change = $this->field('code', 'text', id: $field->id, constraints: ['minLength' => 4]);
    $op = $this->admit(FieldSchemaChange::revise($change));
    $blocked = $this->drain($op);
    expect([$blocked->state, $blocked->blockers])->toBe(['blocked', ['historical-conversion-failed']])
        ->and($blocked->failures)->toHaveCount(1)
        ->and(fn () => $this->schemas->retry($this->scope, $op, $blocked->generation))->toThrow(SchemaConflict::class);
    $corrected = $this->writeValue($field, 'r1', 'good', $original->valueRevision);
    $this->schemas->retry($this->scope, $op, $blocked->generation);
    expect($this->drain($op)->state)->toBe('completed');
    $row = DB::table('sys_attribute_documents')->where($doc)->sole();
    expect($row->document_revision)->toBe($corrected->valueRevision->value)
        ->and(json_decode($row->slots, true, flags: JSON_THROW_ON_ERROR))->toBe([$field->id->value.'@2' => 'good'])
        ->and(DB::table('sys_outbox_messages')->count())->toBe(6);
});

it('persists a zero-CAS retry when a separate verified writer wins after the source snapshot', function (): void {
    $field = $this->field('batch', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $entity = $this->entity;
    $scope = $this->scope;
    $record = new RecordRef($entity->value, 'race');
    $values = $this->app->make(FieldValues::class);
    $schema = $this->catalogue->snapshot($scope, $entity)->revision;
    $write = function (string $input, DocumentRevision $revision, string $key) use ($values, $record, $scope, $schema, $field) {
        $patch = new FieldPatch([new SetField($field->id, FieldInput::from($input))], $schema, $revision);

        return $values->update($scope, $record, $patch, $patch->token($scope, $record, $key));
    };
    $initial = $write('old-value', DocumentRevision::absent(), 'before');
    $op = $this->admit(FieldSchemaChange::revise($this->field('batch', 'text', id: $field->id, constraints: ['minLength' => 3])));
    $this->step();
    $original = DB::getDefaultConnection();
    config(['database.connections.schema_racer' => config('database.connections.'.$original)]);
    $pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
    $seen = false;
    $winner = null;
    DB::listen(function (QueryExecuted $query) use (&$seen, &$winner, $original, $pid, $write, $initial): void {
        if ($seen || ! str_contains($query->sql, ' AS source_value') || $query->connectionName !== $original) {
            return;
        }
        $seen = true;
        DB::setDefaultConnection('schema_racer');

        try {
            expect(DB::selectOne('SELECT pg_backend_pid() AS pid')->pid)->not->toBe($pid);
            $winner = $write('winner-value', $initial->valueRevision, 'after');
        } finally {
            DB::setDefaultConnection($original);
        }
    });
    $this->step();
    $retry = DB::table('sys_attribute_backfills')->where('operation_id', $op->id)->where('kind', 'retry')->sole();
    $document = DB::table('sys_attribute_documents')->where('record_id', 'race')->sole();
    expect($seen)->toBeTrue()->and($retry->seen_revision)->toBe($initial->valueRevision->value)
        ->and($document->document_revision)->toBe($winner->valueRevision->value)
        ->and(array_values(json_decode($document->slots, true, flags: JSON_THROW_ON_ERROR)))->toBe(['winner-value', 'winner-value']);
    $this->step();
    expect(DB::table('sys_attribute_backfills')->where('operation_id', $op->id)->where('kind', 'retry')->count())->toBe(0);
    expect($this->drain($op)->state)->toBe('completed')->and(DB::table('sys_attribute_documents')->where('record_id', 'race')->value('document_revision'))->toBe($winner->valueRevision->value)
        ->and(DB::table('sys_outbox_messages')->where('name', 'fields.values.changed')->count())->toBe(2);
});

it('resumes slow verification and bounded contraction without revisiting an acknowledged prefix at switch', function (): void {
    $field = $this->field('volume', 'text');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    for ($i = 0; $i < 56; $i++) {
        $this->writeValue($field, sprintf('r%03d', $i), 'original');
    }
    $op = $this->admit(FieldSchemaChange::revise($this->field('volume', 'text', id: $field->id, constraints: ['minLength' => 2])));
    $job = fn () => DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    for ($attempt = 0; $attempt < 8 && $job()->phase !== 'verify'; $attempt++) {
        $this->step();
    }
    expect($job()->phase)->toBe('verify');
    $reads = 0;
    $expired = false;
    DB::listen(function (QueryExecuted $query) use (&$reads, &$expired): void {
        if (str_contains($query->sql, ' AS matches')) {
            $reads++;
            $this->clock->offset += $expired ? 31 : 1;
        }
    });
    $this->step();
    $progress = fn () => DB::table('sys_attribute_backfills')->where('operation_id', $op->id)->where('partition_id', 'verify')->sole();
    $prefix = $progress()->cursor_record_id;
    expect($prefix)->toBe('r014')->and($job()->phase)->toBe('verify');
    $expired = true;
    $this->step();
    expect($progress()->cursor_record_id)->toBe($prefix)->and($job()->phase)->toBe('verify');
    $expired = false;
    // A newly inserted key behind the verified prefix is covered by the dual-write rule.
    $this->writeValue($field, 'a-new', 'both');
    for ($attempt = 0; $attempt < 5 && $job()->phase === 'verify'; $attempt++) {
        $this->step();
    }
    expect($job()->phase)->toBe('switch')->and($progress()->exhausted)->toBeTrue()
        ->and($reads)->toBe(57);
    $this->step();
    expect($job()->state)->toBe('switched')->and($reads)->toBe(57);
    $before = DB::table('sys_attribute_documents')->whereRaw('jsonb_exists(slots, ?)', [$field->id->value.'@1'])->count();
    $this->step();
    expect($job()->state)->toBe('contracting')->and($before)->toBe(57)
        ->and(DB::table('sys_attribute_documents')->whereRaw('jsonb_exists(slots, ?)', [$field->id->value.'@1'])->count())->toBe(7);
    $this->step();
    expect($job()->state)->toBe('completed')->and(DB::table('sys_attribute_documents')->whereRaw('jsonb_exists(slots, ?)', [$field->id->value.'@2'])->count())->toBe(57)
        ->and(DB::table('sys_attribute_documents')->whereRaw('jsonb_exists(slots, ?)', [$field->id->value.'@1'])->exists())->toBeFalse();
});
