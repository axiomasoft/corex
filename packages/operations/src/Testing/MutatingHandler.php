<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Illuminate\Database\ConnectionResolverInterface;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MutatingHandler implements OperationHandler
{
    public static int $calls = 0;

    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
    ) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        $operationKey = $input['operation_key'] ?? '';

        if (! is_scalar($operationKey)) {
            throw new InvalidArgumentException('Operation key must be scalar.');
        }

        $this->connections->connection()->table('tool_effects')->insert([
            'account_id' => $context->tenant()->account->id,
            'operation_key' => (string) $operationKey,
            'payload' => json_encode($input, JSON_THROW_ON_ERROR),
            'created_at' => $this->clock->now(),
        ]);

        return ['status' => 'written', 'account' => $context->tenant()->account->id];
    }
}
