<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use Illuminate\Support\Str;

function definition(AttributeId $id, string $key, int $revision = 1, AttributeLifecycle $lifecycle = AttributeLifecycle::Active, array $meta = []): AttributeDefinition
{
    return new AttributeDefinition(
        id: $id,
        entity: 'contact',
        key: $key,
        revision: $revision,
        type: 'text',
        lifecycle: $lifecycle,
        required: false,
        searchable: false,
        constraints: [],
        readKeys: [],
        writeKeys: [],
        index: IndexPolicy::None,
        meta: $meta,
    );
}

it('rejects a new id that is not UUIDv7', function (): void {
    expect(fn () => AttributeId::v7('6ba7b810-9dad-11d1-80b4-00c04fd430c8'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects duplicate keys and invalid lifecycle transitions', function (): void {
    $id = AttributeId::generate();

    expect(fn () => AttributeDefinition::canonicalList([
        definition($id, 'website'),
        definition(AttributeId::generate(), 'website'),
    ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AttributeLifecycle::Active->assertTransition(AttributeLifecycle::Retired))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps a stable hash and the pinned id across a key rename', function (): void {
    $id = AttributeId::v7((string) Str::uuid7());
    $first = definition($id, 'website', meta: ['label' => 'Web']);
    $second = definition($id, 'site', revision: 2, lifecycle: AttributeLifecycle::Migrating, meta: ['label' => 'Web']);

    $forward = AttributeDefinition::schemaHash('contact', [$first, $second]);
    $reverse = AttributeDefinition::schemaHash('contact', [$second, $first]);

    expect($forward)->toBe($reverse)
        ->and($second->id->value)->toBe($id->value)
        ->and($second->snapshotRow()['code'])->toBe('site')
        ->and($second->snapshotRow()['key'])->toBe('site');
});

it('builds the same legacy id for the same entity and key', function (): void {
    expect(AttributeId::legacy('contact', 'website')->value)
        ->toBe(AttributeId::legacy('contact', 'website')->value)
        ->and(AttributeId::legacy('contact', 'website')->isLegacy())->toBeTrue();
});
