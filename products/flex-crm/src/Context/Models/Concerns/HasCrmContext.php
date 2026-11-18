<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Models\Concerns;

use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\CrmScope;
use Flex\Crm\Context\Internal\RecordRules;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Internal\Value;
use Flex\Crm\Lead\Models\Lead;
use Override;
use Packman\Attributes\Internal;

#[Internal]
trait HasCrmContext
{
    public static function bootHasCrmContext(): void
    {
        static::addGlobalScope(new CrmScope);
        static::saving(static fn (Company|Contact|Lead|Deal|Activity $record) => resolve(RecordRules::class)->saving($record));
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getAttributesForInsert(): array
    {
        // Run after all saving/creating observers, including saveQuietly paths.
        resolve(RecordRules::class)->saving($this);

        return Value::map(parent::getAttributesForInsert());
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getDirtyForUpdate(): array
    {
        resolve(RecordRules::class)->saving($this);

        return parent::getDirtyForUpdate();
    }
}
