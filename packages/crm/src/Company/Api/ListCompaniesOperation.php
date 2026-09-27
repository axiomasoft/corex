<?php

declare(strict_types=1);

namespace Flex\Crm\Api;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Flex\Crm\Models\Company;
use Flex\Crm\Support\ContextGuard;

final readonly class ListCompaniesOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $ctx = $this->guard->current();
        $limit = $input['limit'] ?? 50;

        return ['items' => Company::on($ctx->connectionName)->orderBy('name')->limit($limit)->get()
            ->map(static fn (Company $company): array => CompanyPayload::from($company))->all()];
    }
}
