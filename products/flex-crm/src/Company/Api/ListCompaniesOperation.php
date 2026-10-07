<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Api;

use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Internal\Value;

final readonly class ListCompaniesOperation implements OperationHandler
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $input @param array<string, string> $parameters @return array<string, mixed> */
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $ctx = $this->guard->current();
        $limit = $input['limit'] ?? 50;

        return ['items' => Company::on($ctx->connectionName)->tap(static fn ($query) => $query->getQuery()->orderBy('name')->limit(Value::integer($limit)))->get()
            ->map(static fn (Company $company): array => CompanyPayload::from($company))->all()];
    }
}
