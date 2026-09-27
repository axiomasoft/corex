<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use RuntimeException;

final class ProviderKeyHandler implements OperationHandler
{
    public static string $mode = 'timeout';

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return match (self::$mode) {
            'committed' => ['provider_status' => 'done'],
            default => throw new RuntimeException('provider timeout'),
        };
    }
}
