<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Definition\Contracts\OperationDefinitionValidator;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Testing\StubOperationHandler;
use CoreX\Operations\Tests\TestCase;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Exceptions\Compiler\CompilationException;
use Packman\Modules\Internal\Compiler\RegistryCompiler;

uses(TestCase::class);

$schema = static fn (): array => ['type' => 'object', 'additionalProperties' => false];

$operation = (static fn (string $method = 'GET', string $id = 'notes.list'): Api => Api::operation($id, $method, '/notes', StubOperationHandler::class, 'notes.read', $schema(), $schema()));

$compile = static function (Api $api): Api {
    $compiled = resolve(RegistryCompiler::class)->compile([
        ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([$api]),
    ], installedPackages: []);

    return collect($compiled->api)->first(fn (Api $item): bool => $item->mode === 'operation');
};

$defaults = [
    'base' => 1,
    'row' => 0,
    'field' => 0,
    'relation' => 0,
    'attribute_filter' => 0,
    'highlight' => 0,
    'side_effect' => 0,
    'max_units' => 10000,
];

it('keeps legacy declarations on default cost behavior and hash', function () use ($operation, $compile): void {
    $first = $compile($operation());
    $second = $compile($operation());
    $first->declaringModule = 'example/notes';
    $second->declaringModule = 'example/notes';

    expect($first->cost)->toBeNull()
        ->and($first->costExplicit)->toBeFalse()
        ->and(fn () => OperationDefinitionValidator::validate($first))->not->toThrow(CompilationException::class)
        ->and(OperationFactory::fromDeclaration($first, 'fp')->contractHash)
        ->toBe(OperationFactory::fromDeclaration($second, 'fp')->contractHash)
        ->and(OperationFactory::fromDeclaration($first, 'fp')->cost)->toBeNull();
});

it('rejects unknown keys, negative weights, overflow bounds and missing bulk recipes', function (Api $api, string $message) {
    expect(fn () => OperationDefinitionValidator::validate($api))->toThrow(CompilationException::class, $message);
})->with([
    'unknown key' => [$operation()->cost(['tokens' => 1]), 'Unknown cost key'],
    'negative weight' => [$operation()->cost(['row' => -1]), 'Negative cost weight'],
    'overflow bound' => [$operation()->cost(['row' => 10001]), 'Cost overflow'],
    'zero base' => [$operation()->cost(['base' => 0]), 'Cost overflow'],
    'zero max' => [$operation()->cost(['max_units' => 0]), 'Cost overflow'],
    'non integer' => [$operation()->cost(['row' => 1.5]), 'Cost overflow'],
    'missing bulk recipe' => [
        $operation('POST', 'notes.bulk')->asBulkCommand()->idempotency('required')->effect('reconciliation_contract'),
        'requires a cost recipe',
    ],
]);

it('compiles reversed cost declarations to the same canonical recipe and hash', function () use ($operation, $compile, $defaults): void {
    $forward = $compile($operation()->cost(['row' => 2, 'base' => 3, 'field' => 4]));
    $reverse = $compile($operation()->cost(['field' => 4, 'base' => 3, 'row' => 2]));
    $expected = [...$defaults, 'base' => 3, 'row' => 2, 'field' => 4];

    expect($forward->cost)->toBe($expected)
        ->and($reverse->cost)->toBe($expected)
        ->and(array_keys($forward->cost ?? []))->toBe(array_keys($defaults));

    $forward->declaringModule = 'example/notes';
    $reverse->declaringModule = 'example/notes';

    expect(OperationFactory::fromDeclaration($forward, 'fp')->contractHash)
        ->toBe(OperationFactory::fromDeclaration($reverse, 'fp')->contractHash)
        ->and(OperationFactory::fromDeclaration($forward, 'fp')->mode)->toBe(OperationMode::Query)
        ->and(OperationFactory::fromDeclaration($forward, 'fp')->idempotency)->toBe(IdempotencyRequirement::Forbidden);
});

it('accepts an explicit bulk recipe', function () use ($operation, $compile, $defaults): void {
    $bulk = $compile(
        $operation('POST', 'notes.bulk')
            ->asBulkCommand()
            ->idempotency('required')
            ->effect('reconciliation_contract')
            ->cost(['base' => 5, 'row' => 1]),
    );

    expect($bulk->cost)->toBe([...$defaults, 'base' => 5, 'row' => 1])
        ->and($bulk->executionSupported)->toBeFalse();
});
