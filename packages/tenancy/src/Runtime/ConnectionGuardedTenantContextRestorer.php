<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime;

use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use CoreX\Tenancy\GuardedTenantContextRestorer;
use CoreX\Tenancy\TenantContext;
use Illuminate\Database\DatabaseManager;
use Override;

/**
 * Semantic restore stays with {@see GuardedTenantContextRestorer}.
 * This wrapper only checks that the already-open connection matches the scope.
 * It never looks up an account or initializes tenancy from the payload.
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
