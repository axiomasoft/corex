<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OutputMismatchHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['unexpected' => 'field'];
    }
}
