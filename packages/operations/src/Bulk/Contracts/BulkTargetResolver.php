<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Data\Data\JsonDocument;
use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface BulkTargetResolver
{
    public function operationId(RequestContext $context, JsonDocument $input): string;
}
