<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Company\Actions\DeleteCompany;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DeleteCompanyOperation implements OperationHandler
{
    public function __construct(private DeleteCompany $deleteCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array{id: string, deleted: true} */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if ($input !== []) {
            throw ValidationException::withMessages(['company' => 'CRM deletion does not accept fields.']);
        }

        $companyId = CrmIdentifier::fromPath($parameters['companyId']);
        $this->deleteCompany->handle(companyId: $companyId);

        return ['id' => $companyId, 'deleted' => true];
    }
}
