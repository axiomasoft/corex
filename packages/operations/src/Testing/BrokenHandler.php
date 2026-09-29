<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use RuntimeException;

final class BrokenHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        throw new RuntimeException('SQLSTATE secret_password private_table');
    }
}
