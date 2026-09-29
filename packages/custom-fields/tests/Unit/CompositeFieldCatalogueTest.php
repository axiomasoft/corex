<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Internal\Schema\CachedFieldCatalogue;
use CoreX\CustomFields\Internal\Schema\CatalogueConflictPolicy;
use CoreX\CustomFields\Internal\Schema\CompositeFieldCatalogue;
use CoreX\Data\DataScope;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

function catalogueSource(array $fields, string $hash = ''): FieldCatalogue
{
    $schemaHash = $hash !== '' ? $hash : hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));

    return new class($fields, $schemaHash) implements FieldCatalogue
    {
        public function __construct(private array $fields, private string $hash) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            return new FieldSnapshot($entity, $this->hash, $this->hash, $this->fields, ['jsonb' => true]);
        }
    };
}

function catalogueField(
    string $id = '018f0b5d-0000-7000-8000-000000000001',
    string $key = 'website',
    int $revision = 1,
    string $type = 'text',
    bool $required = false,
    string $lifecycle = 'active',
    array $readKeys = [],
): array {
    return [
        'code' => $key,
        'constraints' => [],
        'id' => $id,
        'index' => 'none',
        'key' => $key,
        'lifecycle' => $lifecycle,
        'meta' => [],
        'read_keys' => $readKeys,
        'required' => $required,
        'revision' => $revision,
        'searchable' => true,
        'type' => $type,
        'write_keys' => [],
    ];
}

function composite(array $sources, ?CatalogueConflictPolicy $policy = null): CompositeFieldCatalogue
{
    return new CompositeFieldCatalogue(
        sources: $sources,
        policy: $policy ?? new CatalogueConflictPolicy('policy/1'),
    );
}

it('rejects the same key owned by different identities', function (): void {
    $catalogue = composite([
        'static' => catalogueSource([catalogueField()]),
        'database' => catalogueSource([catalogueField(id: '018f0b5d-0000-7000-8000-000000000002')]),
    ]);

    expect(fn (): mixed => $catalogue->snapshot(jsonbScope(), 'contact'))
        ->toThrow(RuntimeException::class, 'same_key_different_id');
});

it('rejects a renamed identity without a migrating alias', function (): void {
    $catalogue = composite([
        'static' => catalogueSource([catalogueField()]),
        'database' => catalogueSource([catalogueField(key: 'site_url', revision: 2)]),
    ]);

    expect(fn (): mixed => $catalogue->snapshot(jsonbScope(), 'contact'))
        ->toThrow(RuntimeException::class, 'same_id_different_key_without_migrating_alias');
});

it('rejects different types at the same identity and revision', function (): void {
    $catalogue = composite([
        'static' => catalogueSource([catalogueField()]),
        'database' => catalogueSource([catalogueField(type: 'url')]),
    ]);

    expect(fn (): mixed => $catalogue->snapshot(jsonbScope(), 'contact'))
        ->toThrow(RuntimeException::class, 'same_id_revision_different_type');
});

it('rejects different flags at the same identity and revision', function (): void {
    $catalogue = composite([
        'static' => catalogueSource([catalogueField()]),
        'database' => catalogueSource([catalogueField(required: true)]),
    ]);

    expect(fn (): mixed => $catalogue->snapshot(jsonbScope(), 'contact'))
        ->toThrow(RuntimeException::class, 'same_id_revision_flags_constraints_index_differ');
});

it('merges byte-identical definitions once', function (): void {
    $field = catalogueField();
    $snapshot = composite([
        'static' => catalogueSource([$field]),
        'database' => catalogueSource([$field]),
    ])->snapshot(jsonbScope(), 'contact');

    expect($snapshot->activeFields)->toBe([$field]);
});

it('allows only an exact retirement shadow override', function (): void {
    $retired = catalogueField(
        lifecycle: 'retired',
    );
    $replacement = catalogueField();
    $policy = new CatalogueConflictPolicy('policy/2', [[
        'higher' => 'database',
        'lower' => 'static',
        'attribute_id' => $replacement['id'],
        'allowed_difference' => 'retirement_shadow',
    ]]);

    $snapshot = composite([
        'static' => catalogueSource([$retired]),
        'database' => catalogueSource([$replacement]),
        'runtime' => catalogueSource([$replacement]),
    ], $policy)->snapshot(jsonbScope(), 'contact');

    expect($snapshot->activeFields)->toBe([$replacement]);
});

it('uses a migrating alias to accept a newer renamed revision', function (): void {
    $renamed = catalogueField(
        key: 'site_url',
        revision: 2,
        lifecycle: 'migrating',
        readKeys: ['website'],
    );
    $snapshot = composite([
        'static' => catalogueSource([catalogueField()]),
        'database' => catalogueSource([$renamed]),
    ])->snapshot(jsonbScope(), 'contact');

    expect($snapshot->activeFields)->toBe([$renamed]);
});

it('fails closed and evicts a malformed cached snapshot', function (): void {
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')->once()->andReturn(['entity' => 'contact']);
    $cache->shouldReceive('forget')->once()->andReturnTrue();
    $catalogue = new CachedFieldCatalogue(
        source: catalogueSource([catalogueField()]),
        cache: $cache,
        sourceFingerprint: 'sources/1',
        policyVersion: 'policy/1',
    );

    expect(fn (): mixed => $catalogue->snapshot(jsonbScope(), 'contact'))
        ->toThrow(RuntimeException::class, 'malformed');
});

it('does not reuse a snapshot after the source fingerprint changes', function (): void {
    $cache = new Repository(new ArrayStore);
    $original = catalogueField();
    $changed = catalogueField(type: 'url');

    $first = new CachedFieldCatalogue(
        source: catalogueSource([$original]),
        cache: $cache,
        sourceFingerprint: 'sources/1',
        policyVersion: 'policy/1',
    );
    $first->snapshot(jsonbScope(), 'contact');

    $second = new CachedFieldCatalogue(
        source: catalogueSource([$changed]),
        cache: $cache,
        sourceFingerprint: 'sources/2',
        policyVersion: 'policy/1',
    );

    expect($second->snapshot(jsonbScope(), 'contact')->activeFields)->toBe([$changed]);
});

it('falls back to the authoritative source when cache access fails', function (): void {
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')->once()->andThrow(new RuntimeException('cache unavailable'));
    $field = catalogueField();
    $catalogue = new CachedFieldCatalogue(
        source: catalogueSource([$field]),
        cache: $cache,
        sourceFingerprint: 'sources/1',
        policyVersion: 'policy/1',
    );

    expect($catalogue->snapshot(jsonbScope(), 'contact')->activeFields)->toBe([$field]);
});
