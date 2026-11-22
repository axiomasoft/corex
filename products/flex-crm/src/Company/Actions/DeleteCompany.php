<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Actions;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DeleteCompany
{
    public function __construct(private ContextGuard $guard) {}

    public function handle(string $companyId): void
    {
        $this->guard->id($companyId);
        $context = $this->guard->current();

        $this->guard->connection($context)->transaction(function () use ($companyId, $context): void {
            $this->guard->current(lock: true);
            $company = Company::on($context->connectionName)
                ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
                ->findOrFail($companyId);

            if ($company->workspace_id !== $this->guard->workspace(ctx: $context, accountLevel: $company->workspace_id === null)) {
                throw new AuthorizationException('CRM cannot delete a company in another workspace.');
            }

            if ($company->delete() !== true) {
                throw ValidationException::withMessages(['company' => 'CRM deletion was vetoed by an observer.']);
            }
        });
    }
}
