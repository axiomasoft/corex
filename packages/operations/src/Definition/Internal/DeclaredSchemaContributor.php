<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Definition\Contracts\SchemaContributor;
use CoreX\Operations\Definition\Data\Operation;
use Override;

/** @internal */
final class DeclaredSchemaContributor implements SchemaContributor
{
    #[Override]
    public function response(Operation $operation): array
    {
        return JsonDocuments::map($operation->responseSchema);
    }
}
