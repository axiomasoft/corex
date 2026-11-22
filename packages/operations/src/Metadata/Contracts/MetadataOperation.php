<?php

declare(strict_types=1);

namespace CoreX\Operations\Metadata\Contracts;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Metadata\Data\MetadataView;
use Packman\Attributes\Internal;

#[Internal]
interface MetadataOperation extends OperationHandler
{
    /** @param array<string, string> $parameters */
    public function describe(RequestContext $context, array $parameters): MetadataView;
}
