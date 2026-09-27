<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Illuminate\Support\Facades\DB;

final class MutatingHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        DB::table('tool_effects')->insert([
            'account_id' => $context->tenant()->account->id,
            'operation_key' => (string) ($input['operation_key'] ?? ''),
            'payload' => json_encode($input, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return ['status' => 'written', 'account' => $context->tenant()->account->id];
    }
}
