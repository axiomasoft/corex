<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Exceptions\ScopeCapabilityMismatch;
use CoreX\Access\Tests\Stubs\Project;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D114 — no capability declared AND no matching physical column: nothing to
// narrow by, so the letter of §4 п.3 applies literally: normalize to account.
it('normalizes own/dept/dept_tree/workspace to account when neither the capability nor the column exists', function (Scope $scope): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'availability.bare',
        model: Project::class,
        table: 'projects', // no owner_id/workspace_id column (tests/database/migrations)
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    expect(new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.bare', $scope))->toBe(Scope::Account);
})->with(fn (): array => [Scope::Own, Scope::Dept, Scope::DeptTree, Scope::Workspace]);

// D114 — declaration drift: the column DOES exist, so the missing capability
// is a bug in the entity's own registration, not a case to silently widen.
it('throws ScopeCapabilityMismatch when the column exists but the capability was never declared', function (): void {
    Schema::create('availability_drift_probe', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('owner_id')->nullable();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'availability.drift',
        model: Project::class,
        table: 'availability_drift_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    expect(fn () => new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.drift', Scope::Own))
        ->toThrow(ScopeCapabilityMismatch::class);
});

it('leaves the scope unchanged once the matching capability is declared', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'availability.declared',
        model: Project::class,
        table: 'projects',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::Ownable],
        fields: [],
        titleAttribute: null,
    ));

    expect(new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.declared', Scope::Own))->toBe(Scope::Own);
});

// Fail-closed for an entity_key the registry does not know at all — never "the whole scope".
it('normalizes to none for an entity_key unknown to the registry', function (): void {
    $registry = new ArrayEntityRegistry;

    expect(new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.unknown', Scope::Own))->toBe(Scope::None);
});

it('leaves none/account untouched regardless of capability or registration', function (): void {
    $registry = new ArrayEntityRegistry;

    expect(new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.unknown', Scope::Account))->toBe(Scope::Account)
        ->and(new ScopeAvailability($registry, resolve(ConnectionResolverInterface::class))->normalize('availability.unknown', Scope::None))->toBe(Scope::None);
});
