<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Database\ConnectionInterface;

final readonly class PhysicalMarker implements PhysicalBindingVerifier
{
    public function __construct(private ?CloudTopology $topology = null) {}

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        if ($this->topology !== null) {
            $this->topology->validate($binding, $context, $connection);

            return;
        }

        self::assert($binding, $connection, config('database.connections.scenario_a.database'));
    }

    public static function assert(PhysicalBinding $binding, ConnectionInterface $connection, string $database): void
    {
        $actual = $connection->selectOne('SELECT current_database() AS name, current_schema() AS namespace');
        $row = $connection->table('scenario_physical_binding')->first();

        if ($actual->name !== $database || $actual->namespace !== 'public' || $row === null
            || $row->binding_id !== $binding->bindingId || (int) $row->generation !== $binding->bindingGeneration
            || $row->incarnation !== $binding->accountIncarnation) {
            throw new ScopeContextMismatch('The physical PostgreSQL identity differs from the independent owner binding.');
        }
    }
}
