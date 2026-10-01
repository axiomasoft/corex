<?php

declare(strict_types=1);

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\ProblemRegistryException;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Errors\Internal\PackageProblemDefinitions;
use CoreX\Errors\Internal\ProblemContribution;
use CoreX\Errors\Laravel\ProblemDefinitions;

test('ProblemRegistry boots with the isolated kernel and freezes its catalogue', function (): void {
    $registry = app(ProblemRegistry::class);
    expect($registry->get(CoreErrorCode::InternalError)->type)->toBe('urn:corex:problem:core.internal_error')
        ->and($registry)->toBe(app(ProblemRegistry::class))
        ->and(array_filter($registry->all(), static fn (ProblemDefinition $row): bool => ! str_starts_with($row->code->value(), 'core.')))->toBe([]);
    ProblemDefinitions::register($this->app, dirname(__DIR__, 2).'/composer.json', PackageProblemDefinitions::class);
    expect(fn () => ProblemDefinitions::register($this->app, dirname(__DIR__, 2).'/composer.json', 'CoreX\\Errors\\Internal\\LateProvider'))
        ->toThrow(ProblemRegistryException::class, 'ERROR-REGISTRY-FROZEN');
});

test('ProblemRegistry rejects invalid boot contributions with typed diagnostics', function (string $failure, array $definitions, string $owner): void {
    $provider = new class($definitions) implements ProblemDefinitionProvider
    {
        public function __construct(private array $rows) {}

        public function definitions(): iterable
        {
            return $this->rows;
        }
    };

    try {
        new CompiledProblemRegistry([new ProblemContribution($owner, $provider)]);
        $this->fail('Invalid boot contribution was accepted.');
    } catch (ProblemRegistryException $error) {
        expect($error->getMessage())->toStartWith($failure)
            ->and($error->errorDescriptor()->category)->toBe(ErrorCategory::Internal)
            ->and($error->errorDescriptor()->code)->toStartWith('core.');
    }
})->with(function (): array {
    $valid = new ProblemDefinition(CoreErrorCode::InternalError, 'urn:corex:problem:core.internal_error', 500, 'Safe failure.', false, ErrorCategory::Internal);

    return [
        'duplicate code' => ['ERROR-DUPLICATE-CODE', [$valid, $valid], 'core'],
        'foreign prefix' => ['ERROR-FOREIGN-PREFIX', [$valid], 'foreign'],
        'duplicate URI' => ['ERROR-DUPLICATE-TYPE', [$valid, new ProblemDefinition(CoreErrorCode::UnknownCode, $valid->type, 500, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
        'invalid status' => ['ERROR-INVALID-STATUS', [new ProblemDefinition(CoreErrorCode::InternalError, $valid->type, 200, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
        'invalid URI' => ['ERROR-INVALID-DEFINITION', [new ProblemDefinition(CoreErrorCode::InternalError, 'relative', 500, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
    ];
});

test('ProblemRegistry rejects unknown persisted identities and descriptor category drift', function (): void {
    $registry = app(ProblemRegistry::class);
    expect(fn () => $registry->fromWire('user.secret'))->toThrow(ProblemRegistryException::class, 'ERROR-UNKNOWN-CODE')
        ->and(fn () => $registry->assertOccurrence(new ErrorDescriptor(CoreErrorCode::InternalError, ErrorCategory::Conflict)))
        ->toThrow(ProblemRegistryException::class, 'ERROR-OCCURRENCE-MISMATCH');
});

test('ProblemRegistry constructor types reject dynamic raw strings', function (): void {
    $raw = 'core.internal_error';
    expect(fn () => new ErrorDescriptor($raw, ErrorCategory::Internal))->toThrow(TypeError::class);
});
