<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Values\AttributeWriteGuard;
use CoreX\CustomFields\Internal\Values\FieldValueValidator;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;

/** @param list<array<string, mixed>> $fields */
function p106Catalogue(array $fields, ?string $hash = null): FieldCatalogue
{
    $hash ??= str_repeat('a', 64);
    $snapshot = new FieldSnapshot('contact', 'attributes/2', $hash, $fields, []);

    return new readonly class($snapshot) implements FieldCatalogue
    {
        public function __construct(private FieldSnapshot $snapshot) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return $this->snapshot;
        }
    };
}

/** @return array<string, mixed> */
function p106Field(array $overrides = []): array
{
    return array_replace([
        'code' => 'status',
        'constraints' => [],
        'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10b',
        'key' => 'status',
        'lifecycle' => 'active',
        'read_keys' => ['status'],
        'required' => false,
        'type' => 'text',
        'write_keys' => ['status'],
    ], $overrides);
}

function p106Guard(FieldCatalogue $catalogue, ArrayObject $calls, ?string $deniedAction = null): AttributeWriteGuard
{
    $authorizer = new readonly class($calls, $deniedAction) implements DataScopeAuthorizer
    {
        public function __construct(
            private ArrayObject $calls,
            private ?string $deniedAction,
        ) {}

        public function authorize(
            DataScope $scope,
            RecordRef $record,
            FieldSelection $fields,
            FieldUse $use,
        ): DataScopeAccessDecision {
            $action = match (true) {
                $fields->fields === [] => 'attributes.subject',
                $use === FieldUse::Read => 'attributes.view',
                default => 'attributes.write',
            };
            $this->calls->append(compact('record', 'fields', 'action'));

            return new DataScopeAccessDecision(
                allowed: $action !== $this->deniedAction,
                reasonCode: $action === $this->deniedAction ? 'policy.denied' : 'policy.allowed',
            );
        }
    };

    return new AttributeWriteGuard(
        catalogue: $catalogue,
        authorizer: $authorizer,
        validator: new FieldValueValidator($catalogue),
    );
}

function p106Reason(Closure $operation): string
{
    try {
        $operation();
    } catch (InvalidAttributeValue $exception) {
        expect($exception->getMessage())->toBe('The attribute write is not allowed.')
            ->and($exception->getPrevious())->toBeInstanceOf(ScopeDenied::class);

        /** @var ScopeDenied $reason */
        $reason = $exception->getPrevious();

        return (string) $reason->reasonCode;
    }

    throw new RuntimeException('The guarded operation unexpectedly succeeded.');
}

it('checks record visibility and per-field visibility and write in order', function (): void {
    $calls = new ArrayObject;
    $catalogue = p106Catalogue([p106Field()]);
    $snapshot = p106Guard($catalogue, $calls)->assertCanWrite(
        scope: jsonbScope(),
        record: new RecordRef('contact', 'record-1'),
        patch: new ValuePatch(['status' => 'open']),
    );

    expect($snapshot->schemaHash)->toBe(str_repeat('a', 64))
        ->and(array_column($calls->getArrayCopy(), 'action'))->toBe([
            'attributes.subject',
            'attributes.view',
            'attributes.write',
        ])
        ->and($calls[0]['record']->id)->toBe('record-1')
        ->and($calls[1]['fields']->fields)->toBe([p106Field()['id']]);
});

it('keeps field absence and hidden-field denial externally indistinguishable', function (): void {
    $unknownCalls = new ArrayObject;
    $hiddenCalls = new ArrayObject;
    $catalogue = p106Catalogue([p106Field()]);

    $unknown = fn (): FieldSnapshot => p106Guard($catalogue, $unknownCalls)->assertCanWrite(
        jsonbScope(),
        new RecordRef('contact', 'record-1'),
        new ValuePatch(['unknown' => 'value']),
    );
    $hidden = fn (): FieldSnapshot => p106Guard($catalogue, $hiddenCalls, 'attributes.view')->assertCanWrite(
        jsonbScope(),
        new RecordRef('contact', 'record-1'),
        new ValuePatch(['status' => 'open']),
    );

    expect(p106Reason($unknown))->toBe('attributes.field.missing')
        ->and(p106Reason($hidden))->toBe('attributes.view.denied');
});

it('reports every authority layer with a distinct value-free reason', function (string $action): void {
    $calls = new ArrayObject;
    $catalogue = p106Catalogue([p106Field()]);

    expect(p106Reason(fn (): FieldSnapshot => p106Guard($catalogue, $calls, $action)->assertCanWrite(
        jsonbScope(),
        new RecordRef('contact', 'record-1'),
        new ValuePatch(['status' => 'open']),
    )))->toBe($action.'.denied');
})->with([
    'subject ownership' => 'attributes.subject',
    'field visibility' => 'attributes.view',
    'field write' => 'attributes.write',
]);

it('requires a current schema hash for stale or evolving definitions', function (): void {
    $calls = new ArrayObject;
    $hash = str_repeat('b', 64);
    $catalogue = p106Catalogue([p106Field([
        'lifecycle' => 'migrating',
        'read_keys' => ['legacy_status', 'status'],
        'write_keys' => ['legacy_status', 'status'],
    ])], $hash);
    $guard = p106Guard($catalogue, $calls);

    expect(p106Reason(fn (): FieldSnapshot => $guard->assertCanWrite(
        jsonbScope(),
        new RecordRef('contact', 'record-1'),
        new ValuePatch(['status' => 'open']),
    )))->toBe('attributes.schema.precondition_required')
        ->and(p106Reason(fn (): FieldSnapshot => $guard->assertCanWrite(
            jsonbScope(),
            new RecordRef('contact', 'record-1'),
            new ValuePatch(['status' => 'open'], expectedSchemaHash: str_repeat('c', 64)),
        )))->toBe('attributes.schema.stale');

    $guard->assertCanWrite(
        jsonbScope(),
        new RecordRef('contact', 'record-1'),
        new ValuePatch(['status' => 'open'], expectedSchemaHash: $hash),
    );
});

it('separates type validation from required enum length range format and reference constraints', function (): void {
    $fields = [
        p106Field(['code' => 'score', 'key' => 'score', 'type' => 'int', 'constraints' => ['min' => 1, 'max' => 10]]),
        p106Field(['code' => 'status', 'key' => 'status', 'constraints' => ['enum' => ['open', 'closed']]]),
        p106Field(['code' => 'label', 'key' => 'label', 'required' => true, 'constraints' => ['min_length' => 2, 'max_length' => 8]]),
        p106Field(['code' => 'email', 'key' => 'email', 'type' => 'email', 'constraints' => ['format' => 'email']]),
        p106Field(['code' => 'owner', 'key' => 'owner', 'type' => 'reference', 'constraints' => ['reference_prefix' => 'contact_']]),
    ];
    $catalogue = p106Catalogue($fields);

    foreach ([
        [['score' => 'wrong'], 'attributes.value.type'],
        [['score' => 11], 'attributes.value.constraint'],
        [['status' => 'pending'], 'attributes.value.constraint'],
        [['label' => null], 'attributes.value.constraint'],
        [['label' => 'x'], 'attributes.value.constraint'],
        [['email' => 'not-an-email'], 'attributes.value.type'],
        [['owner' => 'deal_1'], 'attributes.value.constraint'],
    ] as [$values, $reason]) {
        $calls = new ArrayObject;

        expect(p106Reason(fn (): FieldSnapshot => p106Guard($catalogue, $calls)->assertCanWrite(
            jsonbScope(),
            new RecordRef('contact', 'record-1'),
            new ValuePatch($values),
        )))->toBe($reason);
    }
});
