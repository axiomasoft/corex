<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Api;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Internal\Value;

final readonly class CreateContactCompanyOperation implements OperationHandler
{
    public function __construct(private AssociateContactCompany $associateContactCompany) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $link = $this->associateContactCompany->handle(
            contactId: CrmIdentifier::fromPath($parameters['contactId']),
            companyId: CrmIdentifier::fromInput($input['company_id']),
            label: Value::nullableText($input['label'] ?? null),
            isPrimary: Value::boolean($input['is_primary'] ?? false),
        );

        return [
            'id' => Value::text($link->getKey()),
            'contact_id' => $link->contact_id,
            'company_id' => $link->company_id,
            'label' => $link->label,
            'is_primary' => $link->is_primary,
        ];
    }
}
