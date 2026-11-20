<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Support;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final class IdempotencyWorkScope implements CurrentWorkScope, DataScopeFactory
{
    public function __construct(
        private readonly TenantContextResolver $contexts,
        private readonly ConnectionInterface $connection,
        public string $principalId = 'trusted-user',
        public string $principalKind = 'user',
    ) {}

    public function forCurrentPrincipal(TenantContext $context): DataScope
    {
        return new DataScope(
            'installation-test', $context->account->id, $context->workspace?->id, 'fixture-binding', 1,
            'incarnation-1', str_repeat('a', 64), $this->principalKind, $this->principalId,
        );
    }

    public function assert(DataScope $scope, ?ConnectionInterface $connection = null): TenantContext
    {
        $context = $this->contexts->current();

        if ($this->forCurrentPrincipal($context) != $scope || ($connection !== null && $connection !== $this->connection)) {
            throw new RuntimeException('Idempotency scope does not match the active tenant connection.');
        }

        return $context;
    }
}
