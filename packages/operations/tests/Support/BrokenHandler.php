<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use RuntimeException;

final class BrokenHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        throw new RuntimeException('SQLSTATE secret_password private_table');
    }
}
