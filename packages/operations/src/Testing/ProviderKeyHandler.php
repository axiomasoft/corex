<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
