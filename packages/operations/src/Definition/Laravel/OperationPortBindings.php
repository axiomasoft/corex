<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Laravel;

use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Internal\OperationRegistry as CompiledOperations;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Invocation\Contracts\EffectiveCapabilities;
use CoreX\Operations\Invocation\Internal\CapabilityResolver;
use CoreX\Operations\OpenApi\Contracts\OpenApiDocuments;
use CoreX\Operations\OpenApi\Internal\OpenApiDocumentGenerator;
use CoreX\Operations\Versioning\Contracts\ApiProfiles;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationPortBindings
{
    public static function register(Application $app): void
    {
        $app->bind(OpenApiDocuments::class, static fn (Application $app): OpenApiDocuments => $app->make(OpenApiDocumentGenerator::class));
        $app->bind(EffectiveCapabilities::class, static fn (Application $app): EffectiveCapabilities => $app->make(CapabilityResolver::class));
        $app->bind(ApiProfiles::class, static fn (Application $app): ApiProfiles => $app->make(ApiProfileFactory::class));
        $app->bind(ErrorMapping::class, static fn (Application $app): ErrorMapping => $app->make(ErrorMapper::class));
        $app->scoped(OperationRegistry::class, CompiledOperations::class);

    }
}
