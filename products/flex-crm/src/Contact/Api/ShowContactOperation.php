<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Api;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\ContextGuard;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ShowContactOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $contactId = CrmIdentifier::fromPath($parameters['contactId']);
        $this->guard->id($contactId);
        $ctx = $this->guard->current();

        return ContactPayload::from(Contact::on($ctx->connectionName)->findOrFail($contactId));
    }
}
