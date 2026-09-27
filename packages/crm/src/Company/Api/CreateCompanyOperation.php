<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Data\CompanyData;

final readonly class CreateCompanyOperation implements OperationHandler
{
    public function __construct(private CreateCompany $createCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return CompanyPayload::from($this->createCompany->handle(new CompanyData(
            name: $input['name'],
            legalName: $input['legal_name'] ?? null,
            domain: $input['domain'] ?? null,
            inn: $input['inn'] ?? null,
            kpp: $input['kpp'] ?? null,
            ogrn: $input['ogrn'] ?? null,
            phones: $input['phones'] ?? [],
            emails: $input['emails'] ?? [],
            address: $input['address'] ?? null,
            bankDetails: $input['bank_details'] ?? null,
            sourceId: array_key_exists('source_id', $input) && $input['source_id'] !== null ? CrmIdentifier::fromInput($input['source_id']) : null,
            ownerId: array_key_exists('owner_id', $input) && $input['owner_id'] !== null ? CrmIdentifier::fromInput($input['owner_id']) : null,
            accountLevel: $input['account_level'] ?? false,
        )));
    }
}
