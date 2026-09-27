<?php

declare(strict_types=1);

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Modules\Compiler\CompiledEntityField;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\InstalledPackages;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\DefaultSettingDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Extend\EntityField;
use CoreX\Modules\Extend\Filament;
use CoreX\Modules\Extend\Menu;
use CoreX\Modules\Extend\Permissions;
use CoreX\Modules\Extend\Routes;
use CoreX\Modules\Extend\Workflow;
use CoreX\Modules\ModuleDependency;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\Registry\FieldDefinition;
use CoreX\Tests\Stubs\StubEntityAlphaThing;
use CoreX\Tests\Stubs\StubEntityBetaThing;
use CoreX\Tests\Stubs\StubRogueExtender;
use CoreX\Tests\Stubs\Widget;

// Local helpers (not global functions, to avoid cross-file name collisions).
$registryCompilerAlphaManifest = function (): ModuleManifest {
    return ModuleManifest::make('corex/stub-alpha')
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

$registryCompilerBetaManifest = function (array $extenders = []): ModuleManifest {
    return ModuleManifest::make('corex/stub-beta')
        ->title('Stub Beta')
        ->tablePrefix('bet')
        ->requires([ModuleDependency::module('corex/stub-alpha')])
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
    $duplicate = ModuleManifest::make('corex/stub-alpha-clone')->title('Clone')->tablePrefix('alp');

    expect(fn () => (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $duplicate]))
        ->toThrow(CompilationException::class, 'Table prefix [alp]');
});

it('rejects a manifest with no tablePrefix() call — D37, no auto-derivation', function (): void {
    $noPrefix = ModuleManifest::make('corex/stub-no-prefix')->title('No Prefix');

    $thrown = null;

    try {
        (new RegistryCompiler)->compile([$noPrefix]);
    } catch (CompilationException $e) {
        $thrown = $e;
    }

    // Assert the VALIDATION-stage message specifically (P3.7): the module name
    // alone is carried by RegistryCompiler's defensive throws too, so a mutant
    // that removes CompilationValidator's check would still throw a
    // CompilationException naming the module and slip past a bare-name assert.
    // 'missing tablePrefix()' is emitted ONLY by validateRequiredFields — the
    // defensive fall-through throws tablePrefixInvariantBypassed instead — so
    // this pins the validation layer and reddens under the P1.17 saboteur.
    expect($thrown)->toBeInstanceOf(CompilationException::class)
        ->and($thrown->getMessage())->toContain('corex/stub-no-prefix')
        ->and($thrown->getMessage())->toContain('missing tablePrefix()');
});

it('rejects a manifest with no title() call', function (): void {
    $noTitle = ModuleManifest::make('corex/stub-no-title')->tablePrefix('ntt');

    expect(fn () => (new RegistryCompiler)->compile([$noTitle]))
        ->toThrow(CompilationException::class, 'corex/stub-no-title');
});

it('rejects a duplicate entity handle declared by two modules, naming both', function () use ($registryCompilerAlphaManifest): void {
    $clone = ModuleManifest::make('corex/stub-alpha-handle-clone')
        ->title('Alpha Handle Clone')
        ->tablePrefix('ahc')
        ->extends([
            Entity::make(StubEntityAlphaThing::class)->handle('alp.thing')->table('ahc_things'),
        ]);

    expect(fn () => (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $clone]))
        ->toThrow(CompilationException::class, 'Duplicate identity [entity:alp.thing] is declared by both [corex/stub-alpha] and [corex/stub-alpha-handle-clone]');
});

it('rejects requires that do not mirror an installed module', function (): void {
    $orphan = ModuleManifest::make('corex/stub-orphan')
        ->title('Orphan')
        ->tablePrefix('orp')
        ->requires([ModuleDependency::module('ghost/does-not-exist')]);

    expect(fn () => (new RegistryCompiler)->compile([$orphan]))
        ->toThrow(CompilationException::class, 'ghost/does-not-exist');
});

it('resolves requires against installed.json contents, not a hardcoded package list (AC-2)', function (): void {
    // Controlled fixture instead of the monorepo's real vendor/composer/installed.json:
    // base_path()-resolution of the real file is environment-dependent (testbench/CI layout
    // may not mirror a developer's local monorepo checkout — see Pending Work P1.17) and was
    // never the point of AC-2. The injection seam (`$installedJsonPath`) exercises the exact
    // same production parsing/validation path deterministically.
    $installedPackages = InstalledPackages::discover(
        __DIR__.'/../Stubs/installed-with-corex-core.json',
    );

    expect($installedPackages)->toContain('corex/core');

    $requiresInstalled = ModuleManifest::make('corex/stub-requires-real')
        ->title('Requires Real')
        ->tablePrefix('rrl')
        ->requires([ModuleDependency::module('corex/core')]);

    $registry = (new RegistryCompiler)->compile([$requiresInstalled], $installedPackages);

    expect($registry->entities)->toBe([]);
});

it('rejects requires against a package absent from installed.json (AC-2)', function (): void {
    $installedPackages = InstalledPackages::discover(
        __DIR__.'/../Stubs/installed-with-corex-core.json',
    );

    $requiresMissing = ModuleManifest::make('corex/stub-requires-missing')
        ->title('Requires Missing')
        ->tablePrefix('rrm')
        ->requires([ModuleDependency::module('corex/not-installed')]);

    expect(fn () => (new RegistryCompiler)->compile([$requiresMissing], $installedPackages))
        ->toThrow(CompilationException::class);
});

it('collects a cross-module EntityField as sleeping, without failing compilation', function () use ($registryCompilerAlphaManifest, $registryCompilerBetaManifest): void {
    $crossModuleField = EntityField::scalar('alp.thing', 'beta_note', 'string');

    $registry = (new RegistryCompiler)->compile([$registryCompilerAlphaManifest(), $registryCompilerBetaManifest([$crossModuleField])]);

    expect($registry->entityFields)->toHaveCount(1)
        ->and($registry->entityFields[0]->extender->fieldHandle)->toBe($crossModuleField->fieldHandle)
        ->and($registry->entityFields[0]->extender)->not->toBe($crossModuleField)
        ->and($registry->entityFields[0]->declaringModule)->toBe('corex/stub-beta')
        ->and($registry->entityFields[0]->sleeping)->toBeTrue();
});

it('does not mark a same-module EntityField as sleeping', function (): void {
    $ownField = EntityField::scalar('alp.thing', 'own_note', 'string');
    $alpha = ModuleManifest::make('corex/stub-alpha')
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
    $manifest = ModuleManifest::make('corex/stub-ghost-ref')
        ->title('Ghost Ref')
        ->tablePrefix('ghr')
        ->extends([EntityField::scalar('nowhere.ghost', 'x', 'string')]);

    expect(fn () => (new RegistryCompiler)->compile([$manifest]))
        ->toThrow(CompilationException::class, 'nowhere.ghost');
});

it('rejects a third-party extender outside the closed set with an RFC referral', function (): void {
    $manifest = ModuleManifest::make('corex/stub-rogue')
        ->title('Rogue')
        ->tablePrefix('rog')
        ->extends([new StubRogueExtender]);

    expect(fn () => (new RegistryCompiler)->compile([$manifest]))
        ->toThrow(CompilationException::class, 'RFC');
});

// ModuleManifest exercising every one of the 8 extender slices (B-10 §4.2) plus
// the two non-extender slices settings/featureFlags (P1.25) so the
// round-trip covers the whole CompiledRegistry, not just entities.
$registryCompilerFullManifest = function (): ModuleManifest {
    return ModuleManifest::make('corex/stub-full')
        ->title('Stub Full')
        ->tablePrefix('sfl')
        ->extends([
            Entity::make(Widget::class)
                ->handle('sfl.thing')
                ->table('sfl_things')
                ->label('Thing', 'Things')
                ->auditable()
                ->searchable()
                ->fields([new FieldDefinition('name', 'string', required: true, searchable: true, meta: ['max' => 255])]),
            EntityField::scalar('sfl.thing', 'extra', 'string', ['default' => 'x'])->required(),
            Permissions::group('thing', ['view', 'create', 'delete'], 'app')->scopeable(),
            Api::entity('sfl.thing')->readonly()->fields(['name'])->include('extra')->attribute('foo', Widget::class),
            Filament::resources([Widget::class], 'app'),
            Menu::item('app', 'dashboard')->label('Dashboard'),
            Menu::item('app', 'sfl.things')->label('Things')->icon('cube')->after('dashboard')->permission('app.thing.view'),
            Routes::api('routes/api.php')->middleware(['auth', 'throttle'])->prefix('sfl'),
            Workflow::trigger('sfl.thing', ['created', 'updated']),
        ])
        ->settings([
            DefaultSettingDefinition::make('theme', 'default-theme')->sensitive(),
        ])
        ->featureFlags([
            new FeatureFlagDefinition(key: 'sfl.beta_mode', default: false, module: 'corex/stub-full'),
        ]);
};

it('round-trips the whole CompiledRegistry (all 10 slices) through toArray/fromArray (P1.15/P1.25)', function () use ($registryCompilerFullManifest): void {
    $compiled = (new RegistryCompiler)->compile([$registryCompilerFullManifest()]);

    $array = $compiled->toArray();

    // Every slice is present and populated in the plain-array projection.
    expect(array_keys($array))->toBe(['entities', 'entityFields', 'permissions', 'api', 'filament', 'menu', 'routes', 'workflow', 'settings', 'featureFlags', 'listeners', 'schema', 'fingerprint', 'compiler', 'modules', 'extensions', 'replacements'])
        ->and($array['schema'])->toBe(CompiledRegistry::SCHEMA)
        ->and($array['fingerprint'])->toBe($compiled->fingerprint)
        ->and($array['entities'])->toHaveKey('sfl.thing')
        ->and($array['entityFields'])->toHaveCount(1)
        ->and($array['permissions'])->toHaveCount(1)
        ->and($array['api'])->toHaveCount(1)
        ->and($array['filament'])->toHaveCount(1)
        ->and($array['menu'])->toHaveCount(2)
        ->and($array['routes'])->toHaveCount(1)
        ->and($array['workflow'])->toHaveCount(1)
        ->and($array['settings'])->toHaveCount(1)
        ->and($array['settings'][0])->toBe(['namespace' => 'sfl', 'definition' => ['key' => 'theme', 'default' => 'default-theme', 'workspaceScoped' => false, 'userScoped' => false, 'sensitive' => true]])
        ->and($array['featureFlags'])->toHaveCount(1)
        ->and($array['featureFlags'][0])->toBe(['key' => 'sfl.beta_mode', 'default' => false, 'module' => 'corex/stub-full', 'payload' => null]);

    // The array is var_export'able: no object, no __set_state marker anywhere.
    expect(var_export($array, true))->not->toContain('::__set_state');

    // fromArray reconstructs an equivalent registry (extenders rehydrated,
    // enums restored, CompiledEntityField rewrapped, CompiledSettingDefault/
    // FeatureFlagDefinition rebuilt).
    expect(CompiledRegistry::fromArray($array))->toEqual($compiled);
});

it('collects settings/featureFlags off plain manifest properties, not an Extender (P1.25, A68)', function (): void {
    $manifest = ModuleManifest::make('corex/stub-defaults')
        ->title('Stub Defaults')
        ->tablePrefix('sfd')
        ->settings([DefaultSettingDefinition::make('locale', 'en')])
        ->featureFlags([new FeatureFlagDefinition(key: 'sfd.on', default: true)]);

    $compiled = (new RegistryCompiler)->compile([$manifest]);

    expect($compiled->settings)->toHaveCount(1)
        ->and($compiled->settings[0]->namespace)->toBe('sfd')
        ->and($compiled->settings[0]->definition->key)->toBe('locale')
        ->and($compiled->settings[0]->definition->default)->toBe('en')
        ->and($compiled->featureFlags)->toHaveCount(1)
        ->and($compiled->featureFlags[0]->key)->toBe('sfd.on')
        ->and($compiled->featureFlags[0]->default)->toBeTrue();
});

it('runs the closed-set pipeline stages in the declared PipelineStages::ORDER, observably (AC-18)', function () use ($registryCompilerFullManifest): void {
    $observed = [];

    (new RegistryCompiler)->compile(
        [$registryCompilerFullManifest()],
        onStage: function (string $stage) use (&$observed): void {
            $observed[] = $stage;
        },
    );

    // Asserted against a HARDCODED literal, not against PipelineStages::ORDER
    // itself — comparing $observed to the constant it was derived from would
    // pass trivially even if the constant were mutated (both sides would move
    // together). This literal is the actual pinned canon (B-10 §4.2, AC-18);
    // reordering PipelineStages::ORDER changes $observed and diverges from it.
    expect($observed)->toBe([
        Entity::class,
        EntityField::class,
        Permissions::class,
        Api::class,
        Filament::class,
        Menu::class,
        Routes::class,
        Workflow::class,
    ]);
});

it('round-trips a CompiledEntityField, rehydrating the wrapped extender (P1.15)', function (): void {
    $field = new CompiledEntityField(
        extender: EntityField::relation('sfl.thing', 'owner', 'belongsTo', Widget::class)->required()->searchable(),
        declaringModule: 'corex/stub-full',
        sleeping: true,
    );

    $array = $field->toArray();

    expect($array['extender']['fieldHandle'])->toBe('owner')
        ->and($array['extender']['related'])->toBe(Widget::class)
        ->and($array['sleeping'])->toBeTrue()
        ->and(CompiledEntityField::fromArray($array))->toEqual($field);
});
