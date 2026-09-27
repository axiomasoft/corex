<?php

declare(strict_types=1);

use CoreX\Models\BaseModel;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Extend\Menu;
use CoreX\Modules\Extend\Permissions;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\Registry\FieldDefinition;
use CoreX\Modules\Tests\Support\StubToolHandler;

function determinismEntity(string $name, string $prefix, string $handle): Entity
{
    return Entity::make(BaseModel::class)
        ->handle($handle)
        ->table($prefix.'_things')
        ->fields([new FieldDefinition('name', 'string')]);
}

function determinismManifest(string $name, string $prefix, string $handle, array $extenders = []): ModuleManifest
{
    return ModuleManifest::make($name)
        ->title($name)
        ->tablePrefix($prefix)
        ->extends([
            determinismEntity($name, $prefix, $handle),
            ...$extenders,
        ]);
}

it('publishes one fingerprint for shuffled module, declaration and associative order', function (): void {
    $alpha = determinismManifest('example/alpha', 'aaa', 'aaa.thing', [
        Permissions::group('thing', ['view', 'create'], 'app'),
        Menu::item('app', 'home')->label('Home'),
        Menu::item('app', 'things')->label('Things')->after('home'),
    ]);
    $beta = determinismManifest('example/beta', 'bbb', 'bbb.thing', [
        Permissions::group('widget', ['view'], 'app'),
    ]);

    $first = (new RegistryCompiler)->compile([$alpha, $beta], []);
    $second = (new RegistryCompiler)->compile([$beta, $alpha], []);

    expect($second->fingerprint)->toBe($first->fingerprint)
        ->and($second->toArray()['extensions'])->toBe($first->toArray()['extensions'])
        ->and($second->toArray()['modules'])->toBe($first->toArray()['modules'])
        ->and(json_encode($second->toArray()['extensions']))->toBe(json_encode($first->toArray()['extensions']));
});

it('keeps semantic menu order while hashing key-sorted payloads', function (): void {
    $manifest = determinismManifest('example/alpha', 'aaa', 'aaa.thing', [
        Menu::item('app', 'home')->label('Home'),
        Menu::item('app', 'things')->label('Things')->after('home'),
    ]);

    $compiled = (new RegistryCompiler)->compile([$manifest], []);
    $menuIds = array_column(array_filter(
        $compiled->extensions,
        static fn (array $row): bool => $row['point'] === 'menu',
    ), 'id');

    expect($menuIds)->toBe(['app:home', 'app:things'])
        ->and($compiled->menu[0]->handle)->toBe('home')
        ->and($compiled->menu[1]->handle)->toBe('things');
});

it('rejects every replacement error with distinct diagnostics', function (
    callable $manifests,
    string $needle,
): void {
    expect(fn () => (new RegistryCompiler)->compile($manifests(), []))
        ->toThrow(CompilationException::class, $needle);
})->with([
    'missing target' => [
        fn (): array => [
            determinismManifest('example/alpha', 'aaa', 'aaa.thing')
                ->replace('entity', 'missing.thing', 'aaa.thing'),
        ],
        'missing target [missing.thing]',
    ],
    'missing replacement' => [
        fn (): array => [
            determinismManifest('example/alpha', 'aaa', 'aaa.thing')
                ->replace('entity', 'aaa.thing', 'ghost.thing'),
        ],
        'missing replacement [ghost.thing]',
    ],
    'foreign replacement' => [
        fn (): array => [
            determinismManifest('example/alpha', 'aaa', 'aaa.thing'),
            determinismManifest('example/beta', 'bbb', 'bbb.thing')
                ->replace('entity', 'aaa.thing', 'aaa.thing'),
        ],
        'must own replacement [aaa.thing]',
    ],
    'duplicate incoming' => [
        fn (): array => [
            determinismManifest('example/alpha', 'aaa', 'aaa.thing'),
            determinismManifest('example/beta', 'bbb', 'bbb.thing')
                ->replace('entity', 'aaa.thing', 'bbb.thing'),
            determinismManifest('example/gamma', 'ggg', 'ggg.thing')
                ->replace('entity', 'aaa.thing', 'ggg.thing'),
        ],
        'already has an incoming edge',
    ],
    'cycle' => [
        fn (): array => [
            determinismManifest('example/alpha', 'aaa', 'aaa.thing')
                ->replace('entity', 'bbb.thing', 'aaa.thing'),
            determinismManifest('example/beta', 'bbb', 'bbb.thing')
                ->replace('entity', 'aaa.thing', 'bbb.thing'),
        ],
        'Replacement graph on [entity] is cyclic',
    ],
]);

it('removes a replaced target from the effective slice and keeps it in provenance', function (): void {
    $alpha = determinismManifest('example/alpha', 'aaa', 'aaa.thing');
    $beta = determinismManifest('example/beta', 'bbb', 'bbb.thing')
        ->replace('entity', 'aaa.thing', 'bbb.thing');

    $compiled = (new RegistryCompiler)->compile([$alpha, $beta], []);

    expect($compiled->entities)->toHaveKey('bbb.thing')
        ->and($compiled->entities)->not->toHaveKey('aaa.thing')
        ->and($compiled->replacements)->toBe([
            ['point' => 'entity', 'target' => 'aaa.thing', 'replacement' => 'bbb.thing', 'owner' => 'example/beta'],
        ]);
});

it('rejects duplicate normalized route identity and names both owners', function (): void {
    $operation = static fn (): Api => Api::operation(
        id: 'notes.show',
        method: 'GET',
        path: '/notes/{id}',
        handler: StubToolHandler::class,
        ability: 'notes.view',
        requestSchema: ['type' => 'object', 'additionalProperties' => false],
        responseSchema: ['type' => 'object', 'additionalProperties' => false],
    );

    $alpha = ModuleManifest::make('example/alpha')->title('A')->tablePrefix('aaa')->extends([$operation()]);
    $beta = ModuleManifest::make('example/beta')->title('B')->tablePrefix('bbb')->extends([$operation()]);

    expect(fn () => (new RegistryCompiler)->compile([$alpha, $beta], []))
        ->toThrow(CompilationException::class, 'Duplicate identity [api.operation:v1:notes.show]');
});

it('rejects a menu missing afterHandle target and a menu cycle', function (array $extenders, string $needle): void {
    $manifest = determinismManifest('example/alpha', 'aaa', 'aaa.thing', $extenders);

    expect(fn () => (new RegistryCompiler)->compile([$manifest], []))
        ->toThrow(CompilationException::class, $needle);
})->with([
    'missing after' => [
        [Menu::item('app', 'things')->after('dashboard')],
        'missing afterHandle [dashboard]',
    ],
    'cycle' => [
        [
            Menu::item('app', 'home')->after('things'),
            Menu::item('app', 'things')->after('home'),
        ],
        'Menu afterHandle graph is cyclic',
    ],
]);

it('rejects unknown schema, missing fingerprint and hash mismatch', function (array $mutate, string $needle): void {
    $compiled = (new RegistryCompiler)->compile([
        determinismManifest('example/alpha', 'aaa', 'aaa.thing'),
    ], []);
    $array = $compiled->toArray();

    foreach ($mutate as $key => $value) {
        if ($value === '__unset') {
            unset($array[$key]);
        } else {
            $array[$key] = $value;
        }
    }

    expect(fn () => CompiledRegistry::fromArray($array))
        ->toThrow(CompilationException::class, $needle);
})->with([
    'old artifact' => [['schema' => '__unset'], 'missing schema'],
    'unknown schema' => [['schema' => 'corex-registry/0'], 'unknown schema'],
    'missing fingerprint' => [['fingerprint' => '__unset'], 'missing fingerprint'],
    'mismatch' => [['fingerprint' => str_repeat('a', 64)], 'fingerprint mismatch'],
]);

it('does not let a hydrated mutation change authority bytes or fingerprint', function (): void {
    $manifest = determinismManifest('example/alpha', 'aaa', 'aaa.thing', [
        Menu::item('app', 'home')->label('Home'),
    ]);
    $compiled = (new RegistryCompiler)->compile([$manifest], []);
    $before = $compiled->toArray();
    $fingerprint = $compiled->fingerprint;

    $items = $compiled->menu;
    $hydrated = $items[0];
    $hydrated->handle = 'mutated';
    $hydrated->label = 'Mutated';

    expect($compiled->fingerprint)->toBe($fingerprint)
        ->and($compiled->toArray())->toBe($before)
        ->and($compiled->toArray()['menu'][0]['handle'])->toBe('home');

    $again = (new RegistryCompiler)->compile([$manifest], []);

    expect($again->fingerprint)->toBe($fingerprint)
        ->and($again->menu[0]->handle)->toBe('home');
});
