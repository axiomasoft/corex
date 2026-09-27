<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\SchemaContributor;
use CoreX\Api\Operation;
use Override;

/** @internal */
final class DeclaredSchemaContributor implements SchemaContributor
{
    #[Override]
    public function response(Operation $operation): array
    {
        return $operation->responseSchema;
    }
}
