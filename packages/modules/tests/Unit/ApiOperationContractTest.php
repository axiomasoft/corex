<?php

declare(strict_types=1);

use CoreX\Api\Enums\IdempotencyRequirement;
use CoreX\Api\Enums\OperationMode;
use CoreX\Api\Operation;
use CoreX\Modules\Compiler\ApiOperationValidator;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\Tests\Support\StubToolHandler;

$schema = static fn (): array => ['type' => 'object', 'additionalProperties' => false];

$operation = static function (string $method = 'GET', string $id = 'notes.list') use ($schema): Api {
    return Api::operation($id, $method, '/notes', StubToolHandler::class, 'notes.read', $schema(), $schema());
};

$compile = static function (Api $api): Api {
    $compiled = (new RegistryCompiler)->compile([
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
        ->and(fn () => ApiOperationValidator::validate($get))->not->toThrow(CompilationException::class);
});

it('rejects illegal mode, method and idempotency pairs', function (Api $api): void {
    expect(fn () => ApiOperationValidator::validate($api))->toThrow(CompilationException::class);
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
    expect(fn () => ApiOperationValidator::validate($operation()->fields(['id', 'id'])))
        ->toThrow(CompilationException::class, 'duplicate read field')
        ->and(fn () => ApiOperationValidator::validate($operation('POST')->requiresScopes('crm.write', 'crm.write')->idempotency('required')->effect('tenant_transactional')))
        ->toThrow(CompilationException::class, 'duplicate scope')
        ->and(fn () => ApiOperationValidator::validate($operation()->capability('CRM.Write')))
        ->toThrow(CompilationException::class, 'Invalid capability')
        ->and(fn () => ApiOperationValidator::validate(tap($operation(), fn (Api $api) => $api->deprecation = ['message' => 'gone'])))
        ->toThrow(CompilationException::class, 'Invalid deprecation')
        ->and(fn () => ApiOperationValidator::validate($operation('POST')->idempotency('required')))
        ->toThrow(CompilationException::class, 'Unsafe idempotent effect')
        ->and(fn () => ApiOperationValidator::validate($operation('POST')->idempotency('required')->effect('direct_external')))
        ->toThrow(CompilationException::class, 'Unsafe idempotent effect');
});

it('marks bulk modes unsupported and refuses enabled execution', function () use ($operation): void {
    $bulk = $operation('POST', 'notes.bulk')->asBulkCommand()->idempotency('required')->effect('reconciliation_contract');

    expect($bulk->executionSupported)->toBeFalse()
        ->and($bulk->bulkExecutionEnabled)->toBeFalse();

    $bulk->bulkExecutionEnabled = true;

    expect(fn () => ApiOperationValidator::validate($bulk))->toThrow(CompilationException::class, 'cannot enable execution');
});

it('projects the same contract hash when declaration order is reversed', function () use ($schema): void {
    $make = static fn (string $id, string $method): Api => Api::operation(
        $id,
        $method,
        '/notes',
        StubToolHandler::class,
        'notes.read',
        $schema(),
        $schema(),
    )->fields(read: ['id', 'name'], write: ['name']);

    $compile = static function (array $extenders): array {
        $compiled = (new RegistryCompiler)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($extenders),
        ], installedPackages: []);

        return [
            Operation::fromDeclaration(
                collect($compiled->api)->first(fn (Api $api): bool => $api->operationId === 'notes.list'),
                $compiled->fingerprint,
            ),
            Operation::fromDeclaration(
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
