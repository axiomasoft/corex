<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Events\CompanyCreated;
use Flex\Crm\Models\Company;
use Illuminate\Support\Facades\DB;

final class CreateCompany
{
    /** @param array<string, mixed>|null $customFields */
    public function handle(
        string $name,
        ?string $ownerId = null,
        ?string $legalName = null,
        ?string $inn = null,
        ?string $kpp = null,
        ?string $website = null,
        ?array $customFields = null,
    ): Company {
        return DB::transaction(function () use ($name, $ownerId, $legalName, $inn, $kpp, $website, $customFields): Company {
            $company = Company::query()->create([
                'name' => $name,
                'owner_id' => $ownerId,
                'legal_name' => $legalName,
                'inn' => $inn,
                'kpp' => $kpp,
                'website' => $website,
                'custom_fields' => $customFields,
            ]);

            CompanyCreated::dispatch($company);

            return $company;
        });
    }
}
