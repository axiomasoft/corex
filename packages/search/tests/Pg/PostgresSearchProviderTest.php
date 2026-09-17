<?php

declare(strict_types=1);

use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use CoreX\Search\Support\SearchQuery;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Search\Tests\PgTestCase;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class);

beforeEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS corex_search_provider_test');
    DB::statement('CREATE TABLE corex_search_provider_test (id varchar(32) primary key, title text not null, status varchar(16) not null)');
    DB::table('corex_search_provider_test')->insert([
        ['id' => 'equipment-1', 'title' => 'Centrifugal water pump', 'status' => 'active'],
        ['id' => 'equipment-2', 'title' => 'Archived pump', 'status' => 'archived'],
    ]);
});

it('uses allowlisted schema identifiers and bound text-query values', function (): void {
    $schema = new class implements SearchSchema
    {
        public function describe(DataScope $scope, string $entity): SearchSnapshot
        {
            return new SearchSnapshot(
                entity: $entity,
                schemaHash: str_repeat('a', 64),
                table: 'corex_search_provider_test',
                idColumn: 'id',
                searchableColumns: ['title'],
                filterColumns: ['status' => 'status'],
            );
        }
    };
    $provider = new PostgresSearchProvider(connection: DB::connection(), schemas: $schema, cursors: new SearchCursorCodec(secret: str_repeat('c', 32)));
    $scope = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a',
        actorId: 'operator-a',
        authorize: static fn (): bool => true,
        restoreEpoch: str_repeat('b', 64),
    );

    $page = $provider->query(
        scope: $scope,
        query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump'),
    );

    expect($page->candidates)->toHaveCount(2)
        ->and($page->candidates[0]->record->id)->toBe('equipment-1');
})->group('pg');

it('uses an opaque cursor bound to its scope and query', function (): void {
    DB::table('corex_search_provider_test')->insert([
        'id' => 'equipment-3', 'title' => 'Portable pump', 'status' => 'active',
    ]);
    $schema = new class implements SearchSchema
    {
        public function describe(DataScope $scope, string $entity): SearchSnapshot
        {
            return new SearchSnapshot(entity: $entity, schemaHash: str_repeat('a', 64), table: 'corex_search_provider_test', idColumn: 'id', searchableColumns: ['title']);
        }
    };
    $provider = new PostgresSearchProvider(connection: DB::connection(), schemas: $schema, cursors: new SearchCursorCodec(secret: str_repeat('c', 32)));
    $scope = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', authorize: static fn (): bool => true, restoreEpoch: str_repeat('b', 64),
    );

    $first = $provider->query(scope: $scope, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', limit: 1));

    expect($first->nextAfterId)->toStartWith('v1.')
        ->and($first->nextAfterId)->not->toContain('equipment-');

    $second = $provider->query(scope: $scope, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', limit: 1, afterId: $first->nextAfterId));

    expect($second->candidates[0]->record->id)->not->toBe($first->candidates[0]->record->id);
})->group('pg');

it('keeps physically separate account databases and their cursors isolated', function (): void {
    foreach (['search_a' => 'corex_search_a_test', 'search_b' => 'corex_search_b_test'] as $name => $database) {
        config()->set("database.connections.{$name}", [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST') ?: '127.0.0.1', 'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database, 'username' => getenv('PGSQL_USERNAME') ?: 'corex', 'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8', 'prefix' => '', 'schema' => 'public',
        ]);
        DB::purge($name);
        DB::connection($name)->statement('DROP TABLE IF EXISTS corex_search_physical_test');
        DB::connection($name)->statement('CREATE TABLE corex_search_physical_test (id varchar(32) primary key, title text not null)');
    }
    DB::connection('search_a')->table('corex_search_physical_test')->insert([
        ['id' => 'account-a-1', 'title' => 'Hydraulic pump'], ['id' => 'account-a-2', 'title' => 'Backup pump'],
    ]);
    DB::connection('search_b')->table('corex_search_physical_test')->insert([
        ['id' => 'account-b-1', 'title' => 'B-only pump'],
    ]);
    $schema = new class implements SearchSchema
    {
        public function describe(DataScope $scope, string $entity): SearchSnapshot
        {
            return new SearchSnapshot(entity: $entity, schemaHash: str_repeat('a', 64), table: 'corex_search_physical_test', idColumn: 'id', searchableColumns: ['title']);
        }
    };
    $providerA = new PostgresSearchProvider(connection: DB::connection('search_a'), schemas: $schema, cursors: new SearchCursorCodec(secret: str_repeat('c', 32)));
    $providerB = new PostgresSearchProvider(connection: DB::connection('search_b'), schemas: $schema, cursors: new SearchCursorCodec(secret: str_repeat('c', 32)));
    $scopeA = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-a', slug: 'account-a', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-a', actorId: 'operator-a', authorize: static fn (): bool => true, restoreEpoch: str_repeat('b', 64),
    );
    $scopeB = new DataScope(
        context: new TenantContext(account: new AccountRef(id: 'account-b', slug: 'account-b', status: 'active', features: [], limits: []), boxed: false),
        connectionIdentity: 'pgsql:account-b', actorId: 'operator-a', authorize: static fn (): bool => true, restoreEpoch: str_repeat('b', 64),
    );
    $firstA = $providerA->query(scope: $scopeA, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', limit: 1));

    expect($providerB->query(scope: $scopeB, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump'))->candidates[0]->record->id)->toBe('account-b-1')
        ->and(fn (): mixed => $providerB->query(scope: $scopeB, query: new SearchQuery(entity: 'equipment', schemaHash: str_repeat('a', 64), text: 'pump', afterId: $firstA->nextAfterId)))->toThrow(InvalidArgumentException::class);
})->group('pg');
