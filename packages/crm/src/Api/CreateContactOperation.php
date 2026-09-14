<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Data\ContactData;

final readonly class CreateContactOperation implements OperationHandler
{
    public function __construct(private CreateContact $createContact) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return ContactPayload::from($this->createContact->handle(new ContactData(
            nameFirst: $input['name_first'] ?? null,
            nameLast: $input['name_last'] ?? null,
            nameMiddle: $input['name_middle'] ?? null,
            jobTitle: $input['job_title'] ?? null,
            birthday: $input['birthday'] ?? null,
            phones: $input['phones'] ?? [],
            emails: $input['emails'] ?? [],
            sourceId: array_key_exists('source_id', $input) && $input['source_id'] !== null ? CrmIdentifier::fromInput($input['source_id']) : null,
            ownerId: array_key_exists('owner_id', $input) && $input['owner_id'] !== null ? CrmIdentifier::fromInput($input['owner_id']) : null,
            accountLevel: $input['account_level'] ?? false,
        )));
    }
}
