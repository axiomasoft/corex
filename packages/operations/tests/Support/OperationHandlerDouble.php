<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Support;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;

final class OperationHandlerDouble implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}
