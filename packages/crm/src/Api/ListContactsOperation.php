<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Models\Contact;
use Flex\Crm\Support\ContextGuard;

final readonly class ListContactsOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $ctx = $this->guard->current();
        $limit = $input['limit'] ?? 50;

        return ['items' => Contact::on($ctx->connectionName)->orderBy('display_name')->limit($limit)->get()
            ->map(static fn (Contact $contact): array => ContactPayload::from($contact))->all()];
    }
}
