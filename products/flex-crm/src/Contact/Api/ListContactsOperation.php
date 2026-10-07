<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Api;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Internal\Value;

final readonly class ListContactsOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $ctx = $this->guard->current();
        $limit = $input['limit'] ?? 50;

        return ['items' => Contact::on($ctx->connectionName)->tap(static fn ($query) => $query->getQuery()->orderBy('display_name')->limit(Value::integer($limit)))->get()
            ->map(static fn (Contact $contact): array => ContactPayload::from($contact))->all()];
    }
}
