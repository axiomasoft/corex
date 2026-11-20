<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Database\DatabaseManager;
use Packman\Modules\Contracts\Entities\EntityRegistry;

final readonly class SearchScenarioEntityActivity implements EntityActivity
{
    public function __construct(
        private EntityActivity $installed,
        private EntityRegistry $entities,
        private TenantContextResolver $context,
        private DatabaseManager $database,
    ) {}

    public function isActive(string $entityKey): bool
    {
        if ($entityKey !== SearchScenarioRecord::ENTITY) {
            return $this->installed->isActive($entityKey);
        }
        $context = $this->context->current();

        return $context->id() === Scenario::BOXED
            && in_array($context->workspace?->id, [Scenario::W1, Scenario::W2], true)
            && $this->entities->has($entityKey)
            && $this->entities->get($entityKey)->model === SearchScenarioRecord::class
            && $this->database->connection()->getSchemaBuilder()->hasTable('scenario_search_records');
    }
}
