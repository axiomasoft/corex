<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Data\CompanyData;
use Flex\Crm\Events\CompanyCreated;
use Flex\Crm\Models\Company;
use Flex\Crm\Support\ContextGuard;

final readonly class CreateCompany
{
    public function __construct(private ContextGuard $guard) {}

    public function handle(CompanyData $data): Company
    {
        $ctx = $this->guard->current();

        return $this->guard->connection($ctx)->transaction(function () use ($data, $ctx): Company {
            $this->guard->current(lock: true);
            $company = new Company([
                'workspace_id' => $this->guard->workspace(ctx: $ctx, accountLevel: $data->accountLevel),
                'name' => $data->name, 'legal_name' => $data->legalName, 'domain' => $data->domain,
                'inn' => $data->inn, 'kpp' => $data->kpp, 'ogrn' => $data->ogrn,
                'phones' => $data->phones, 'emails' => $data->emails,
                'address' => $data->address, 'bank_details' => $data->bankDetails,
                'source_id' => $data->sourceId, 'owner_id' => $data->ownerId,
            ]);
            $company->setConnection($ctx->connectionName);
            $company->save();
            CompanyCreated::dispatch($company, $ctx->actor);

            return $company;
        });
    }
}
