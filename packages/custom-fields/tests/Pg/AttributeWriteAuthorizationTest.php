<?php

declare(strict_types=1);

use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\ValueFixture;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\RemoveField;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('reauthorizes field and record eligibility before serving old tokens and filters unavailable batch entries', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $revoked = false;
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static function ($scope, RecordRef $record, FieldSelection $fields, FieldUse $use) use (&$revoked): bool {
        return $record->id !== 'hidden' && ! ($revoked && $fields->fields !== [] && $use === FieldUse::Write);
    }));
    $values = $this->app->make(FieldValues::class);
    $record = new RecordRef('cas.equipment', 'open');
    $scope = jsonbScope();
    $patch = $fixture->patch('allowed');
    $token = $patch->token($scope, $record, 'first');
    $values->update($scope, $record, $patch, $token);
    $revoked = true;
    expect(fn () => $values->update($scope, $record, $patch, $token))->toThrow(FieldFailure::class, 'access-denied');
    $batch = $values->readMany($scope, new RecordSelection([$record, new RecordRef('cas.equipment', 'hidden')]), new FieldSelection([$fixture->field->id->value]));
    expect($batch->items)->toHaveCount(1)->and($batch->rejections)->toBe([['position' => 1, 'reason' => 'unavailable']])
        ->and($batch->items[0]->values[$fixture->field->id->value]->document->canonicalJson)->toBe('"allowed"')
        ->and(DB::table('sys_outbox_messages')->count())->toBe(1);
});

it('projects each authorized value of a visible record and conceals denied and unknown selections alike', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $hidden = $fixture->schema->field('secondary')->id;
    $missing = '01900000-0000-7000-8000-000000000003';
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static fn ($scope, $record, FieldSelection $fields, FieldUse $use): bool => $use !== FieldUse::Read || ! in_array($hidden->value, $fields->fields, true)));
    $record = new RecordRef('cas.equipment', 'visible');
    $scope = jsonbScope();
    $values = $this->app->make(FieldValues::class);
    $entry = $fixture->patch('visible')->operations[0];
    $patch = new FieldPatch([$entry, new SetField($hidden, FieldInput::from('hidden'))],
        $fixture->schema->revision, DocumentRevision::absent());
    $values->update($scope, $record, $patch, $patch->token($scope, $record, 'both'));
    $visible = $values->read($scope, $record, new FieldSelection([$fixture->field->id->value, $hidden->value, $missing]));
    expect(array_keys($visible->values))->toBe([$fixture->field->id->value])
        ->and($values->read($scope, $record, new FieldSelection([$missing]))->values)->toBe([])
        ->and($values->read($scope, $record, new FieldSelection([$hidden->value]))->values)->toBe([]);
});

it('denies a record before its head, document or token is read', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static fn ($scope, $record, $fields, $use): bool => $use !== FieldUse::Read));
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'closed');
    $patch = $fixture->patch('v');
    DB::connection()->enableQueryLog();
    expect(fn () => $this->app->make(FieldValues::class)->update($scope, $ref, $patch, $patch->token($scope, $ref, 'token')))->toThrow(FieldFailure::class, 'access-denied');
    expect(DB::connection()->getQueryLog())->toBe([]);
});

it('rejects a stale physical scope before record admission or any owner SQL', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $scope = jsonbScope('other-binding');
    $ref = new RecordRef('cas.equipment', 'target');
    $patch = $fixture->patch('v');
    $calls = 0;
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static function () use (&$calls): bool {
        $calls++;

        return true;
    }));
    DB::connection()->enableQueryLog();
    expect(fn () => $this->app->make(FieldValues::class)->update($scope, $ref, $patch, $patch->token($scope, $ref, 'wrong-binding')))
        ->toThrow(ScopeContextMismatch::class)
        ->and($calls)->toBe(0)->and(DB::connection()->getQueryLog())->toBe([]);
});

it('returns the same write failure for a hidden UUID and an unknown UUID', function (): void {
    $fixture = new ValueFixture;
    $fixture->bind($this->app);
    $denied = $fixture->schema->field('secondary')->id;
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static fn ($scope, $ref, FieldSelection $selection): bool => ! in_array($denied->value, $selection->fields, true)));
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'target');
    $values = $this->app->make(FieldValues::class);
    foreach ([$denied, new FieldId('01900000-0000-7000-8000-000000000003')] as $field) {
        $patch = new FieldPatch([new RemoveField($field)], $fixture->schema->revision, DocumentRevision::absent());
        expect(fn () => $values->update($scope, $ref, $patch, $patch->token($scope, $ref, 'same')))->toThrow(FieldFailure::class, 'access-denied');
    }
    expect(DB::table('sys_attribute_write_tokens')->count())->toBe(0);
});

it('checks the current referenced record on read, filter, and an exact write replay', function (): void {
    $fixture = new ValueFixture('reference', ['target' => 'cas.person']);
    $fixture->bind($this->app);
    $allow = true;
    $this->app->instance(DataScopeAuthorizer::class, jsonbAuthorizer(static function ($scope, RecordRef $record) use (&$allow): bool {
        return $record->entity !== 'cas.person' || $allow;
    }));
    $scope = jsonbScope();
    $ref = new RecordRef('cas.equipment', 'asset');
    $target = ['entity' => 'cas.person', 'id' => 'owner'];
    $patch = $fixture->patch($target);
    $token = $patch->token($scope, $ref, 'assign');
    $values = $this->app->make(FieldValues::class);
    $values->update($scope, $ref, $patch, $token);
    $allow = false;
    $node = new FilterNode($fixture->field->id, FieldOperator::Eq, FieldInput::from($target));
    expect($values->read($scope, $ref, new FieldSelection([$fixture->field->id->value]))->values)->toBe([])
        ->and(fn () => $values->update($scope, $ref, $patch, $token))->toThrow(FieldFailure::class, 'access-denied')
        ->and(fn () => $this->app->make(FieldFilter::class)->compile($scope, $fixture->schema, $node))->toThrow(FieldFailure::class, 'access-denied')
        ->and(DB::table('sys_outbox_messages')->count())->toBe(1);
});
