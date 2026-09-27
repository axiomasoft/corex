<?php

declare(strict_types=1);

use CoreX\Api\Data\ToolDescriptor;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\Tests\Support\StubToolHandler;

$objectSchema = static fn (): array => [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [
        'name' => ['type' => 'string'],
    ],
];

it('compiles explicit tool declarations into the existing API slice (DESC-01)', function () use ($objectSchema): void {
    $manifest = ModuleManifest::make('example/tools')
        ->title('Tools')
        ->tablePrefix('tls')
        ->extends([
            Api::tool(
                code: 'notes.create',
                handler: StubToolHandler::class,
                ability: 'notes.create',
                inputSchema: $objectSchema(),
                outputSchema: $objectSchema(),
                sideEffect: 'write',
                idempotency: 'domain-key',
            )->titled('Create note', 'Creates a note'),
        ]);

    $compiled = (new RegistryCompiler)->compile([$manifest], installedPackages: []);
    $tool = collect($compiled->api)->first(fn (Api $api): bool => $api->mode === 'tool');

    expect($tool)->not->toBeNull()
        ->and($tool->declaringModule)->toBe('example/tools')
        ->and($tool->toolCode)->toBe('notes.create')
        ->and(CompiledRegistry::fromArray($compiled->toArray()))->toEqual($compiled);

    $descriptor = ToolDescriptor::fromDeclaration($tool);
    expect($descriptor->module)->toBe('example/tools')
        ->and($descriptor->wireName)->toBe('notes_create__v1');
});

it('does not expose unannotated operations as tools (DESC-02)', function () use ($objectSchema): void {
    $manifest = ModuleManifest::make('example/tools')
        ->title('Tools')
        ->tablePrefix('tls')
        ->extends([
            Api::operation(
                id: 'notes.list',
                method: 'GET',
                path: '/notes',
                handler: StubToolHandler::class,
                ability: 'notes.read',
                requestSchema: $objectSchema(),
                responseSchema: $objectSchema(),
            ),
        ]);

    $compiled = (new RegistryCompiler)->compile([$manifest], installedPackages: []);

    expect(collect($compiled->api)->contains(fn (Api $api): bool => $api->mode === 'tool'))->toBeFalse();
});

it('keeps stable tool tuple and contract hash across declaration order (DESC-03)', function () use ($objectSchema): void {
    $first = Api::tool('alpha.first', StubToolHandler::class, 'alpha.read', $objectSchema(), $objectSchema(), 'read', 'read-only')
        ->titled('First', 'First tool');
    $second = Api::tool('beta.second', StubToolHandler::class, 'beta.read', $objectSchema(), $objectSchema(), 'read', 'read-only')
        ->titled('Second', 'Second tool');

    $forward = (new RegistryCompiler)->compile([
        ModuleManifest::make('example/alpha')->title('Alpha')->tablePrefix('alp')->extends([$first, $second]),
    ], installedPackages: []);
    $reverse = (new RegistryCompiler)->compile([
        ModuleManifest::make('example/alpha')->title('Alpha')->tablePrefix('alp')->extends([$second, $first]),
    ], installedPackages: []);

    $forwardHash = ToolDescriptor::fromDeclaration(collect($forward->api)->first(fn (Api $api): bool => $api->toolCode === 'alpha.first'))->contractHash;
    $reverseHash = ToolDescriptor::fromDeclaration(collect($reverse->api)->first(fn (Api $api): bool => $api->toolCode === 'alpha.first'))->contractHash;

    expect($forwardHash)->toBe($reverseHash);
});

it('rejects invalid canonical namespaces and wire collisions (DESC-04)', function () use ($objectSchema): void {
    expect(fn () => (new RegistryCompiler)->compile([
        ModuleManifest::make('example/bad')->title('Bad')->tablePrefix('bad')->extends([
            Api::tool('bad_namespace.create', StubToolHandler::class, 'bad.read', $objectSchema(), $objectSchema(), 'read', 'read-only'),
        ]),
    ], installedPackages: []))->toThrow(CompilationException::class);

    expect(fn () => (new RegistryCompiler)->compile([
        ModuleManifest::make('example/bad')->title('Bad')->tablePrefix('bad')->extends([
            Api::tool('bad.create', StubToolHandler::class, 'bad.read', $objectSchema(), $objectSchema(), 'read', 'read-only'),
            Api::tool('bad.create', StubToolHandler::class, 'bad.read', $objectSchema(), $objectSchema(), 'read', 'read-only', toolVersion: 'v1'),
        ]),
    ], installedPackages: []))->toThrow(CompilationException::class);
});

it('rejects duplicate tuples, foreign operation refs and invalid handlers (DESC-05)', function () use ($objectSchema): void {
    $operation = Api::operation('notes.list', 'GET', '/notes', StubToolHandler::class, 'notes.read', $objectSchema(), $objectSchema());

    expect(fn () => (new RegistryCompiler)->compile([
        ModuleManifest::make('example/dup')->title('Dup')->tablePrefix('dup')->extends([
            Api::tool('dup.read', StubToolHandler::class, 'dup.read', $objectSchema(), $objectSchema(), 'read', 'read-only'),
            Api::tool('dup.read', StubToolHandler::class, 'dup.read', $objectSchema(), $objectSchema(), 'read', 'read-only'),
        ]),
    ], installedPackages: []))->toThrow(CompilationException::class);

    expect(fn () => (new RegistryCompiler)->compile([
        ModuleManifest::make('example/expose')->title('Expose')->tablePrefix('exp')->extends([
            $operation,
            Api::exposeTool('foreign.list', 'v1', 'exp.read', 'v1', 'read', 'read-only', $objectSchema(), $objectSchema(), ['name' => 'name']),
        ]),
    ], installedPackages: []))->toThrow(CompilationException::class);

    expect(fn () => (new RegistryCompiler)->compile([
        ModuleManifest::make('example/handler')->title('Handler')->tablePrefix('hdl')->extends([
            Api::tool('hdl.read', stdClass::class, 'hdl.read', $objectSchema(), $objectSchema(), 'read', 'read-only'),
        ]),
    ], installedPackages: []))->toThrow(CompilationException::class);
});

it('compiles exposeTool against a same-module operation without adding HTTP routes (DESC-01 expose)', function () use ($objectSchema): void {
    $manifest = ModuleManifest::make('example/expose')->title('Expose')->tablePrefix('exp')->extends([
        Api::operation('notes.list', 'GET', '/notes', StubToolHandler::class, 'notes.read', $objectSchema(), $objectSchema()),
        Api::exposeTool('notes.list', 'v1', 'notes.list', 'v1', 'read', 'read-only', $objectSchema(), $objectSchema(), ['name' => 'name']),
    ]);

    $compiled = (new RegistryCompiler)->compile([$manifest], installedPackages: []);
    $tools = collect($compiled->api)->where('mode', 'tool')->values();
    $operations = collect($compiled->api)->where('mode', 'operation')->values();

    expect($tools)->toHaveCount(1)
        ->and($operations)->toHaveCount(1)
        ->and($tools[0]->operationRef)->toBe('v1:notes.list')
        ->and($tools[0]->handler)->toBe(StubToolHandler::class);
});
