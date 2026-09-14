<?php

declare(strict_types=1);

namespace Flex\Crm\Models\Concerns;

use Flex\Crm\Support\CrmScope;
use Flex\Crm\Support\RecordRules;

trait HasCrmContext
{
    public static function bootHasCrmContext(): void
    {
        static::addGlobalScope(new CrmScope);
        static::saving(static fn ($record) => app(RecordRules::class)->saving($record));
    }
}
