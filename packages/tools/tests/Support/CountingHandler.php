<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Contracts\ToolRequestContext;

final class CountingHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return [
            'name' => (string) ($input['name'] ?? 'note'),
            'actor' => $context->principal()->principalId(),
            'account' => $context->tenant()->account->id,
            'operation_key' => $context instanceof ToolRequestContext ? $context->operationKey() : null,
            'contract_hash' => $context instanceof ToolRequestContext ? $context->toolContractHash() : null,
        ];
    }
}
