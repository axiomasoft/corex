<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\DataScope;
use Illuminate\Database\DatabaseManager;
use Override;

/**
 * Semantic restore stays with the core-owned {@see TenantContextRestorer} binding.
 * This wrapper only checks that the already-open connection matches the scope.
 * It never looks up an account or initializes tenancy from the payload.
 *
 * @internal
 */
final readonly class ConnectionGuardedTenantContextRestorer implements TenantContextRestorer
{
    public function __construct(
        private TenantContextRestorer $semantic,
        private DatabaseManager $database,
    ) {}

    #[Override]
    public function restore(DataScope $scope): TenantContext
    {
        $current = $this->semantic->restore($scope);
        $actual = $this->activeIdentity($current);

        if ($actual !== $scope->connectionIdentity) {
            throw ScopeContextMismatch::connection(expected: $scope->connectionIdentity, actual: $actual);
        }

        return $current;
    }

    private function activeIdentity(TenantContext $current): string
    {
        if ($current->isBoxed()) {
            return 'boxed:'.$current->account->id;
        }

        $connection = $this->database->connection($this->database->getDefaultConnection());

        return $connection->getDriverName().':'.$connection->getDatabaseName();
    }
}
