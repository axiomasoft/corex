<?php

declare(strict_types=1);

use CoreX\Tests\Stubs\StubEntityAlphaThing;
use CoreX\Tests\Stubs\StubEntityBetaThing;
use CoreX\Tests\Stubs\StubRogueExtender;
use Flex\Modules\Compiler\RegistryCompiler;
use Flex\Modules\Dep;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Exceptions\CompilationException;
use Flex\Modules\Extend\Entity;
use Flex\Modules\Extend\EntityField;
use Flex\Modules\Manifest;

// Local helpers (not global functions, to avoid cross-file name collisions).
$registryCompilerAlphaManifest = function (): Manifest {
    return Manifest::make('corex/stub-alpha')
        ->title('Stub Alpha')
        ->tablePrefix('alp')
        ->extends([
            Entity::make(StubEntityAlphaThing::class)
                ->handle('alp.thing')
                ->table('alp_things')
                ->label('Thing', 'Things')
                ->auditable(),
        ]);
};

$registryCompilerBetaManifest = function (array $extenders = []): Manifest {
    return Manifest::make('corex/stub-beta')
        ->title('Stub Beta')
        ->tablePrefix('bet')
        ->requires([Dep::module('corex/stub-alpha')])
        ->extends([
            Entity::make(StubEntityBetaThing::class)
                ->handle('bet.thing')
                ->table('bet_things'),
            ...$extenders,
        ]);
};

it('compiles two stub modules with EntityDefinitions in pipeline order', function () use ($registryCompilerAlphaManifest, $registryCompilerBetaManifest): void {
    $registry = (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $registryCompilerBetaManifest()]);

    expect(array_keys($registry->entities))->toBe(['alp.thing', 'bet.thing'])
        ->and($registry->entities['alp.thing']->model)->toBe(StubEntityAlphaThing::class)
        ->and($registry->entities['alp.thing']->capabilities)->toBe([Capability::Auditable])
        ->and(array_column($registry->entities['alp.thing']->fields, 'handle'))->toContain('note')
        ->and($registry->entities['bet.thing']->model)->toBe(StubEntityBetaThing::class);
});

it('rejects a duplicate tablePrefix across manifests', function () use ($registryCompilerAlphaManifest): void {
    $duplicate = Manifest::make('corex/stub-alpha-clone')->title('Clone')->tablePrefix('alp');

    expect(fn () => (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $duplicate]))
        ->toThrow(CompilationException::class, 'Table prefix [alp]');
});

it('rejects requires that do not mirror an installed module', function (): void {
    $orphan = Manifest::make('corex/stub-orphan')
        ->title('Orphan')
        ->requires([Dep::module('ghost/does-not-exist')]);

    expect(fn () => (new RegistryCompiler)->compile([$orphan]))
        ->toThrow(CompilationException::class, 'ghost/does-not-exist');
});

it('collects a cross-module EntityField as sleeping, without failing compilation', function () use ($registryCompilerAlphaManifest, $registryCompilerBetaManifest): void {
    $crossModuleField = EntityField::scalar('alp.thing', 'beta_note', 'string');

    $registry = (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $registryCompilerBetaManifest([$crossModuleField])]);

    expect($registry->entityFields)->toHaveCount(1)
        ->and($registry->entityFields[0]->extender)->toBe($crossModuleField)
        ->and($registry->entityFields[0]->declaringModule)->toBe('corex/stub-beta')
        ->and($registry->entityFields[0]->sleeping)->toBeTrue();
});

it('does not mark a same-module EntityField as sleeping', function (): void {
    $ownField = EntityField::scalar('alp.thing', 'own_note', 'string');
    $alpha = Manifest::make('corex/stub-alpha')
        ->title('Stub Alpha')
        ->tablePrefix('alp')
        ->extends([
            Entity::make(StubEntityAlphaThing::class)->handle('alp.thing')->table('alp_things'),
            $ownField,
        ]);

    $registry = (new RegistryCompiler)->compile([$alpha]);

    expect($registry->entityFields[0]->sleeping)->toBeFalse();
});

it('rejects an EntityField targeting a handle unknown to any compiled manifest', function (): void {
    $manifest = Manifest::make('corex/stub-ghost-ref')
        ->title('Ghost Ref')
        ->extends([EntityField::scalar('nowhere.ghost', 'x', 'string')]);

    expect(fn () => (new RegistryCompiler)->compile([$manifest]))
        ->toThrow(CompilationException::class, 'nowhere.ghost');
});

it('rejects a third-party extender outside the closed set with an RFC referral', function (): void {
    $manifest = Manifest::make('corex/stub-rogue')
        ->title('Rogue')
        ->extends([new StubRogueExtender]);

    expect(fn () => (new RegistryCompiler)->compile([$manifest]))
        ->toThrow(CompilationException::class, 'RFC');
});
