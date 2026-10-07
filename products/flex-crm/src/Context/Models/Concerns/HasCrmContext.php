<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Models\Concerns;

use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\Context\CrmScope;
use Flex\Crm\Context\Internal\RecordRules;
use Flex\Crm\Internal\Value;
use Override;

trait HasCrmContext
{
    public static function bootHasCrmContext(): void
    {
        static::addGlobalScope(new CrmScope);
        static::saving(static fn (\Flex\Crm\Company\Models\Company|\Flex\Crm\Contact\Models\Contact|\Flex\Crm\Lead\Models\Lead|\Flex\Crm\Deal\Models\Deal|Activity $record) => app(RecordRules::class)->saving($record));
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getAttributesForInsert(): array
    {
        // Run after all saving/creating observers, including saveQuietly paths.
        app(RecordRules::class)->saving($this);

        return Value::map(parent::getAttributesForInsert());
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getDirtyForUpdate(): array
    {
        app(RecordRules::class)->saving($this);

        return parent::getDirtyForUpdate();
    }
}
