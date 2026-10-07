<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Internal\Value;

final class CompanyPayload
{
    /** @return array<string, mixed> */
    public static function from(Company $company): array
    {
        return [
            'id' => Value::text($company->getKey()),
            'workspace_id' => $company->workspace_id === null ? null : (string) $company->workspace_id,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'domain' => $company->domain,
            'inn' => $company->inn,
            'kpp' => $company->kpp,
            'ogrn' => $company->ogrn,
            'phones' => $company->phones,
            'emails' => $company->emails,
            'address' => $company->address,
            'bank_details' => $company->bank_details,
            'source_id' => $company->source_id === null ? null : (string) $company->source_id,
            'owner_id' => $company->owner_id === null ? null : $company->owner_id,
        ];
    }
}
