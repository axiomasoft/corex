<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Internal\Value;

final readonly class CreateCompanyOperation implements OperationHandler
{
    public function __construct(private CreateCompany $createCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return CompanyPayload::from($this->createCompany->handle(new CompanyData(
            name: Value::text($input['name']),
            legalName: Value::nullableText($input['legal_name'] ?? null),
            domain: Value::nullableText($input['domain'] ?? null),
            inn: Value::nullableText($input['inn'] ?? null),
            kpp: Value::nullableText($input['kpp'] ?? null),
            ogrn: Value::nullableText($input['ogrn'] ?? null),
            phones: Value::maps($input['phones'] ?? []),
            emails: Value::maps($input['emails'] ?? []),
            address: Value::nullableMap($input['address'] ?? null),
            bankDetails: Value::nullableMap($input['bank_details'] ?? null),
            sourceId: array_key_exists('source_id', $input) && $input['source_id'] !== null ? CrmIdentifier::fromInput($input['source_id']) : null,
            ownerId: array_key_exists('owner_id', $input) && $input['owner_id'] !== null ? CrmIdentifier::fromInput($input['owner_id']) : null,
            accountLevel: Value::boolean($input['account_level'] ?? false),
        )));
    }
}
