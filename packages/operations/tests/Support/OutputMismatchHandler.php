<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;

final class OutputMismatchHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['unexpected' => 'field'];
    }
}
