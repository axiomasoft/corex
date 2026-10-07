<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Actions;

use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Company\Events\CompanyCreated;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Validation\ValidationException;

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

            if (! $company->save()) {
                throw ValidationException::withMessages(['company' => 'CRM creation was vetoed by an observer.']);
            }
            event(new CompanyCreated($company, $ctx->actor));

            return $company;
        });
    }
}
