<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Actions;

use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Events\ContactCompanyAssociated;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Contact\Models\ContactCompany;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AssociateContactCompany
{
    public function __construct(private ContextGuard $guard) {}

    public function handle(string|int $contactId, string|int $companyId, ?string $label = null, bool $isPrimary = false): ContactCompany
    {
        $this->guard->id($contactId);
        $this->guard->id($companyId);

        if ($label !== null && (trim($label) === '' || mb_strlen($label) > 64)) {
            throw ValidationException::withMessages(['label' => 'Association label must contain 1–64 characters or be NULL.']);
        }
        $ctx = $this->guard->current();

        return $this->guard->connection($ctx)->transaction(function () use ($ctx, $contactId, $companyId, $label, $isPrimary): ContactCompany {
            $this->guard->current(lock: true);
            $contact = Contact::on($ctx->connectionName)->whereKey($contactId)->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->firstOrFail();
            $company = Company::on($ctx->connectionName)->whereKey($companyId)->tap(static fn ($query) => $query->getQuery()->sharedLock())->firstOrFail();

            if ($contact->workspace_id !== $company->workspace_id) {
                throw new AuthorizationException('CRM association requires identical workspace scopes.');
            }
            $links = ContactCompany::on($ctx->connectionName)->where('contact_id', $contactId);

            if ((clone $links)->where('company_id', $companyId)->toBase()->exists() || ($isPrimary && (clone $links)->where('is_primary', true)->toBase()->exists())) {
                throw ValidationException::withMessages(['association' => 'The contact/company pair or primary association already exists.']);
            }
            $link = new ContactCompany(['contact_id' => $contactId, 'company_id' => $companyId, 'label' => $label === null ? null : trim($label), 'is_primary' => $isPrimary]);
            $link->setConnection($ctx->connectionName);

            if (! $link->save()) {
                throw ValidationException::withMessages(['association' => 'CRM association was vetoed by an observer.']);
            }
            event(new ContactCompanyAssociated($link, $ctx->actor));

            return $link;
        });
    }
}
