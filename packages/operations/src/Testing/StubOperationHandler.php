<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;

final class StubOperationHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['ok' => true, 'input' => $input, 'parameters' => $parameters];
    }
}
