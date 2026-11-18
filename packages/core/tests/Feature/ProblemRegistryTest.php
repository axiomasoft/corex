<?php

declare(strict_types=1);

use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Internal\PackageProblemDefinitions;
use CoreX\Testing\ProblemRegistryFixture;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\ProblemRegistryException;
use Packman\Errors\Laravel\ProblemDefinitions;

test('ProblemRegistry boots with the isolated kernel and freezes its catalogue', function (): void {
    $registry = resolve(ProblemRegistry::class);
    expect($registry->get(CoreErrorCode::InternalError)->type)->toBe('urn:packman:problem:core.internal_error')
        ->and($registry)->toBe(resolve(ProblemRegistry::class))
        ->and($registry->get(Packman\Errors\Enums\CoreErrorCode::InternalError)->type)->toBe('urn:packman:problem:packman.internal_error');
    ProblemDefinitions::register($this->app, dirname(__DIR__, 2).'/composer.json', PackageProblemDefinitions::class);
    expect(fn () => ProblemDefinitions::register($this->app, dirname(__DIR__, 2).'/composer.json', 'CoreX\\Errors\\Internal\\LateProvider'))
        ->toThrow(ProblemRegistryException::class, 'ERROR-REGISTRY-FROZEN');
});

test('ProblemRegistry rejects invalid boot contributions with typed diagnostics', function (string $failure, array $definitions, string $owner): void {
    $provider = new readonly class($definitions) implements ProblemDefinitionProvider
    {
        public function __construct(private array $rows) {}

        public function definitions(): iterable
        {
            return $this->rows;
        }
    };

    try {
        ProblemRegistryFixture::compile($owner, $provider);
        $this->fail('Invalid boot contribution was accepted.');
    } catch (ProblemRegistryException $error) {
        expect($error->getMessage())->toStartWith($failure)
            ->and($error->errorDescriptor()->category)->toBe(ErrorCategory::Internal)
            ->and($error->errorDescriptor()->code)->toStartWith('packman.');
    }
})->with(function (): array {
    $valid = new ProblemDefinition(CoreErrorCode::InternalError, 'urn:packman:problem:core.internal_error', 500, 'Safe failure.', false, ErrorCategory::Internal);

    return [
        'duplicate code' => ['ERROR-DUPLICATE-CODE', [$valid, $valid], 'core'],
        'foreign prefix' => ['ERROR-FOREIGN-PREFIX', [$valid], 'foreign'],
        'duplicate URI' => ['ERROR-DUPLICATE-TYPE', [$valid, new ProblemDefinition(CoreErrorCode::OutboxRefused, $valid->type, 500, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
        'invalid status' => ['ERROR-INVALID-STATUS', [new ProblemDefinition(CoreErrorCode::InternalError, $valid->type, 200, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
        'invalid URI' => ['ERROR-INVALID-DEFINITION', [new ProblemDefinition(CoreErrorCode::InternalError, 'relative', 500, 'Safe failure.', false, ErrorCategory::Internal)], 'core'],
    ];
});

test('ProblemRegistry rejects unknown persisted identities and descriptor category drift', function (): void {
    $registry = resolve(ProblemRegistry::class);
    expect(fn () => $registry->fromWire('user.secret'))->toThrow(ProblemRegistryException::class, 'ERROR-UNKNOWN-CODE')
        ->and(fn () => $registry->assertOccurrence(new ErrorDescriptor(CoreErrorCode::InternalError, ErrorCategory::Conflict)))
        ->toThrow(ProblemRegistryException::class, 'ERROR-OCCURRENCE-MISMATCH');
});

test('ProblemRegistry constructor types reject dynamic raw strings', function (): void {
    $raw = 'core.internal_error';
    expect(fn () => new ErrorDescriptor($raw, ErrorCategory::Internal))->toThrow(TypeError::class);
});
