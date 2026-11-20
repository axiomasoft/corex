<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\SearchSnapshot;
use RuntimeException;

final class ScenarioSearchSchema implements SearchSchema
{
    public const string ENTITY = 'scenario.equipment';

    public static function hash(): string
    {
        return str_repeat('e', 64);
    }

    public function describe(DataScope $scope, string $entity): SearchSnapshot
    {
        if ($entity !== self::ENTITY) {
            throw new RuntimeException('Unknown scenario search entity.');
        }

        return new SearchSnapshot(
            entity: $entity,
            schemaHash: self::hash(),
            table: 'scenario_records',
            idColumn: 'id',
            searchableColumns: ['marker'],
            resultColumns: [$scope->accountId === Scenario::ACCOUNT_B ? 'tenant_b_label' : 'tenant_a_label'],
        );
    }
}
