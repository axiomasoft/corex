<?php

declare(strict_types=1);

namespace CoreX\Modules\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;

final class StubToolHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['ok' => true, 'input' => $input, 'parameters' => $parameters];
    }
}
