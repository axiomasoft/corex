<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class StubOperationHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['ok' => true, 'input' => $input, 'parameters' => $parameters];
    }
}
