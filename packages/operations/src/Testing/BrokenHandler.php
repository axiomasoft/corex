<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BrokenHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        throw new RuntimeException('SQLSTATE secret_password private_table');
    }
}
