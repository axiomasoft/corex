<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolRequestContext;

final class CountingHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return [
            'name' => (string) ($input['name'] ?? 'note'),
            'actor' => $context->principal()->id(),
            'account' => $context->tenant()->account->id,
            'operation_key' => $context instanceof ToolRequestContext ? $context->operationKey() : null,
            'contract_hash' => $context instanceof ToolRequestContext ? $context->toolContractHash() : null,
        ];
    }
}
