<?php

declare(strict_types=1);

namespace Flex\Crm\Models\Concerns;

use Flex\Crm\Support\CrmScope;
use Flex\Crm\Support\RecordRules;
use Override;

trait HasCrmContext
{
    public static function bootHasCrmContext(): void
    {
        static::addGlobalScope(new CrmScope);
        static::saving(static fn ($record) => app(RecordRules::class)->saving($record));
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getAttributesForInsert(): array
    {
        // Run after all saving/creating observers, including saveQuietly paths.
        app(RecordRules::class)->saving($this);

        return parent::getAttributesForInsert();
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getDirtyForUpdate(): array
    {
        app(RecordRules::class)->saving($this);

        return parent::getDirtyForUpdate();
    }
}
