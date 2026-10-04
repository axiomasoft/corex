<?php

declare(strict_types=1);

namespace CoreX\Errors\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Exceptions\ProblemRegistryException;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Errors\Internal\ProblemContribution;
use Illuminate\Contracts\Foundation\Application;

/** Compiles the problem registry from the tagged package contributions. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ProblemRegistryFactory
{
    public function make(Application $app): CompiledProblemRegistry
    {
        $contributions = [];

        foreach ($app->tagged(ProblemDefinitionProvider::class) as $contribution) {
            if (! $contribution instanceof ProblemContribution) {
                throw new ProblemRegistryException(CoreErrorCode::InvalidDefinition, 'ERROR-INVALID-CONTRIBUTION');
            }

            $contributions[] = $contribution;
        }

        return new CompiledProblemRegistry($contributions);
    }
}
