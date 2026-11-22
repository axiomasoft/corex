<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Actions;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class UpdateCompany
{
    public function __construct(private ContextGuard $guard) {}

    /** @param array<string, mixed> $changes */
    public function handle(string $companyId, array $changes): Company
    {
        $this->guard->id($companyId);

        foreach (array_keys($changes) as $field) {
            if (! in_array($field, ['name', 'legal_name', 'domain'], true)) {
                throw ValidationException::withMessages([$field => 'CRM company field is not writable.']);
            }
        }

        $context = $this->guard->current();

        return $this->guard->connection($context)->transaction(function () use ($companyId, $context, $changes): Company {
            $this->guard->current(lock: true);
            $company = Company::on($context->connectionName)
                ->tap(static fn ($query) => $query->getQuery()->lockForUpdate())
                ->findOrFail($companyId);
            $company->fill($changes);

            if (! $company->save()) {
                throw ValidationException::withMessages(['company' => 'CRM update was vetoed by an observer.']);
            }

            return $company;
        });
    }
}
