<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Internal;

use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\Access\Roles\Laravel\RoleMatrixData;
use CoreX\Foundation\Laravel\ReferenceWrites;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use LogicException;
use Override;
use Packman\Attributes\Internal;

/** Native matrix compilation; listeners share the transaction and can veto it. */
#[Internal]
final readonly class DatabaseRoleMatrixCompiler implements RoleMatrixCompiler
{
    public function __construct(
        private ScopeMapVersion $scopeMapVersion,
        private ConnectionResolverInterface $connections,
        private Dispatcher $events,
    ) {}

    #[Override]
    public function compile(string $roleId): void
    {
        $connection = $this->connections->connection();

        if (! $connection instanceof Connection) {
            throw new LogicException('Matrix compilation requires a transactional Laravel connection.');
        }

        $connection->transaction(function () use ($roleId, $connection): void {
            ReferenceWrites::enlist(connection: $connection, scope: ReferenceWrites::current(), producer: 'axiomasoft/corex-access', kind: 'compile-role', witness: ['role' => $roleId]);
            RoleMatrixData::role($roleId);
            RoleMatrixData::matrix($roleId);

            $this->events->dispatch(new RoleMatrixCompiled($roleId));
            $connection->afterCommit($this->scopeMapVersion->invalidator($roleId));
        });
    }

    #[Override]
    public function recompileAll(): void
    {
        foreach (RoleMatrixData::roleIds() as $roleId) {
            $this->compile($roleId);
        }
    }
}
