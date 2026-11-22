<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Company\Actions\UpdateCompany;
use Packman\Attributes\Internal;

#[Internal]
final readonly class UpdateCompanyOperation implements OperationHandler
{
    public function __construct(private UpdateCompany $updateCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return CompanyPayload::from($this->updateCompany->handle(
            companyId: CrmIdentifier::fromPath($parameters['companyId']),
            changes: $input,
        ));
    }
}
