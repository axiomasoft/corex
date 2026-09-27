<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Models\Company;
use Flex\Crm\Support\ContextGuard;

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
