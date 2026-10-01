<?php

declare(strict_types=1);

namespace CoreX\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Exceptions\ProblemRegistryException;
use Illuminate\Contracts\Foundation\Application;

/** @internal Container wiring belongs to the kernel composition boundary. */
final class ProblemRegistryRegistrar
{
    public static function register(Application $app): void
    {
        $app->singleton(ProblemRegistry::class, static function (Application $app): CompiledProblemRegistry {
            $contributions = [];
            foreach ($app->tagged(ProblemDefinitionProvider::class) as $contribution) {
                if (! $contribution instanceof ProblemContribution) {
                    throw new ProblemRegistryException(CoreErrorCode::InvalidDefinition, 'ERROR-INVALID-CONTRIBUTION');
                }
                $contributions[] = $contribution;
            }

            return new CompiledProblemRegistry($contributions);
        });
        $app->booted(static function (Application $app): void {
            $app->make(ProblemRegistry::class);
        });
    }
}
