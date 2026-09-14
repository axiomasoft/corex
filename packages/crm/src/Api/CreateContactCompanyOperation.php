<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Actions\AssociateContactCompany;

final readonly class CreateContactCompanyOperation implements OperationHandler
{
    public function __construct(private AssociateContactCompany $associateContactCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $link = $this->associateContactCompany->handle(
            contactId: CrmIdentifier::fromPath($parameters['contactId']),
            companyId: CrmIdentifier::fromInput($input['company_id']),
            label: $input['label'] ?? null,
            isPrimary: $input['is_primary'] ?? false,
        );

        return [
            'id' => (string) $link->getKey(),
            'contact_id' => (string) $link->contact_id,
            'company_id' => (string) $link->company_id,
            'label' => $link->label,
            'is_primary' => $link->is_primary,
        ];
    }
}
