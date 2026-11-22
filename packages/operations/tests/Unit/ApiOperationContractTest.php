<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Definition\Contracts\OperationDefinitionValidator;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Testing\StubOperationHandler;
use CoreX\Operations\Tests\TestCase;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Exceptions\Compiler\CompilationException;

uses(TestCase::class);

$schema = static fn (): array => ['type' => 'object', 'additionalProperties' => false];

$operation = (static fn (string $method = 'GET', string $id = 'notes.list'): Api => Api::operation($id, $method, '/notes', StubOperationHandler::class, 'notes.read', $schema(), $schema(), version: 'v2'));

$compile = static function (Api $api): Api {
    $compiled = resolve(ModuleCompilation::class)->compile([
        ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$api]),
    ], installedPackages: []);

    return collect($compiled->api)->first(fn (Api $item): bool => $item->mode === 'operation');
};

it('keeps legacy operation declarations on the compatibility matrix', function () use ($operation, $compile): void {
    $get = $compile($operation());
    $post = $compile($operation('POST', 'notes.create'));

    expect($get->operationMode)->toBe('query')
        ->and($get->idempotencyRequirement)->toBe('forbidden')
        ->and($post->operationMode)->toBe('command')
        ->and($post->idempotencyRequirement)->toBe('optional')
        ->and($post->effectMode)->toBeNull()
        ->and(fn () => OperationDefinitionValidator::validate($get))->not->toThrow(CompilationException::class);
});

it('rejects illegal mode, method and idempotency pairs', function (Api $api): void {
    expect(fn () => OperationDefinitionValidator::validate($api))->toThrow(CompilationException::class);
})->with([
    'query post' => [$operation('POST')->asQuery()],
    'query required' => [$operation()->asQuery()->idempotency('required')->effect('tenant_transactional')],
    'command forbidden' => [$operation('POST')->asCommand()->idempotency('forbidden')],
    'command get' => [$operation()->asCommand()->idempotency('optional')->effect('tenant_transactional')],
    'async optional' => [$operation('POST')->asAsyncCommand()->idempotency('optional')->effect('tenant_transactional')],
    'async status' => [(static function () use ($operation): Api {
        $api = $operation('POST', 'notes.accept')->asAsyncCommand()->idempotency('required')->effect('tenant_transactional');
        $api->successStatus = 200;

        return $api;
    })()],
    'bulk get' => [$operation()->asBulkQuery()->idempotency('required')->effect('tenant_transactional')],
]);

it('rejects duplicate fields and scopes, bad capability and deprecation, and unsafe effects', function () use ($operation): void {
    expect(fn () => OperationDefinitionValidator::validate($operation()->fields(['id', 'id'])))
        ->toThrow(CompilationException::class, 'duplicate read field')
        ->and(fn () => OperationDefinitionValidator::validate($operation('POST')->requiresScopes('crm.write', 'crm.write')->idempotency('required')->effect('tenant_transactional')))
        ->toThrow(CompilationException::class, 'duplicate scope')
        ->and(fn () => OperationDefinitionValidator::validate($operation()->capability('CRM.Write')))
        ->toThrow(CompilationException::class, 'Invalid capability')
        ->and(fn () => OperationDefinitionValidator::validate(tap($operation(), fn (Api $api) => $api->deprecation = ['message' => 'gone'])))
        ->toThrow(CompilationException::class, 'Invalid deprecation')
        ->and(fn () => OperationDefinitionValidator::validate($operation('POST')->idempotency('required')))
        ->toThrow(CompilationException::class, 'Unsafe idempotent effect')
        ->and(fn () => OperationDefinitionValidator::validate($operation('POST')->idempotency('required')->effect('direct_external')))
        ->toThrow(CompilationException::class, 'Unsafe idempotent effect');
});

it('compiles execution support only for an explicitly opted-in bulk command', function () use ($operation, $compile): void {
    $bulk = $operation('POST', 'notes.bulk')->asBulkCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 2]);
    $bulk->successStatus = 202;
    $bulk->executionSupported = true;

    expect($compile($bulk)->executionSupported)->toBeFalse();

    $bulk->bulkExecutionEnabled = true;
    $bulk->executionSupported = false;
    $compiled = $compile($bulk);

    expect($compiled->executionSupported)->toBeTrue()
        ->and($compiled->operationMode)->toBe('bulk_command')
        ->and($compiled->idempotencyRequirement)->toBe('required')
        ->and($compiled->successStatus)->toBe(202)
        ->and($compiled->cost['base'])->toBe(2);
});

it('rejects unsafe execution opt-ins in the real compiler', function (string $control, string $reason) use ($operation, $compile): void {
    $api = $operation('POST', 'notes.bulk')->asBulkCommand()->idempotency('required')->effect('tenant_transactional');
    $api->successStatus = 202;

    if ($control !== 'missing cost') {
        $api->cost(['base' => 2]);
    }
    match ($control) {
        'query' => $api->asBulkQuery(),
        'effect' => $api->effect('reconciliation_contract'),
        'method' => $api->method = 'GET',
        'status' => $api->successStatus = 200,
        'key' => $api->idempotency('optional'),
        'implicit key' => $api->idempotencyExplicit = false,
        'overflow' => $api->cost(['base' => 10_001]),
        default => null,
    };
    $api->bulkExecutionEnabled = true;

    expect(fn () => $compile($api))->toThrow(CompilationException::class, $reason);
})->with([
    ['query', 'cannot enable execution'],
    ['effect', 'cannot enable execution'],
    ['method', 'Illegal operation contract'],
    ['status', 'cannot enable execution'],
    ['key', 'Illegal operation contract'],
    ['implicit key', 'cannot enable execution'],
    ['missing cost', 'requires a cost recipe'],
    ['overflow', 'Cost overflow'],
]);

it('projects the same contract hash when declaration order is reversed', function () use ($schema): void {
    $make = static fn (string $id, string $method): Api => Api::operation(
        $id,
        $method,
        '/notes',
        StubOperationHandler::class,
        'notes.read',
        $schema(),
        $schema(),
        version: 'v2',
    )->fields(read: ['id', 'name'], write: ['name']);

    $compile = static function (array $extenders): array {
        $compiled = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($extenders),
        ], installedPackages: []);

        return [
            OperationFactory::fromDeclaration(
                collect($compiled->api)->first(fn (Api $api): bool => $api->operationId === 'notes.list'),
                $compiled->fingerprint,
            ),
            OperationFactory::fromDeclaration(
                collect($compiled->api)->first(fn (Api $api): bool => $api->operationId === 'notes.create'),
                $compiled->fingerprint,
            ),
        ];
    };

    [$forwardList, $forwardCreate] = $compile([$make('notes.list', 'GET'), $make('notes.create', 'POST')]);
    [$reverseList, $reverseCreate] = $compile([$make('notes.create', 'POST'), $make('notes.list', 'GET')]);

    expect($forwardList->contractHash)->toBe($reverseList->contractHash)
        ->and($forwardCreate->contractHash)->toBe($reverseCreate->contractHash)
        ->and($forwardList->mode)->toBe(OperationMode::Query)
        ->and($forwardList->idempotency)->toBe(IdempotencyRequirement::Forbidden)
        ->and($forwardCreate->mode)->toBe(OperationMode::Command)
        ->and($forwardList->readFields)->toBe(['id', 'name'])
        ->and($forwardList->executionSupported)->toBeTrue();
});

it('reuses tagged compiler contributions across repeated compilations', function (): void {
    $compiler = resolve(ModuleCompilation::class);
    $manifest = ModuleManifest::make('example/repeat')->title('Repeat')->tablePrefix('rpt')->extends([
        Api::operation('repeat.read', 'GET', '/repeat', StubOperationHandler::class, 'repeat.read', ['type' => 'object'], ['type' => 'object'], version: 'v2'),
    ]);

    expect($compiler->compile([$manifest], installedPackages: [])->fingerprint)
        ->toBe($compiler->compile([$manifest], installedPackages: [])->fingerprint);
});
