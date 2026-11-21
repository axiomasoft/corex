<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;

final class Ping implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ['marker' => 'ordinary-operation'];
    }
}
