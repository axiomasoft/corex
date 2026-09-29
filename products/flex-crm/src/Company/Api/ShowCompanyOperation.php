<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;

final readonly class ShowCompanyOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $companyId = CrmIdentifier::fromPath($parameters['companyId']);
        $this->guard->id($companyId);
        $ctx = $this->guard->current();

        return CompanyPayload::from(Company::on($ctx->connectionName)->findOrFail($companyId));
    }
}
