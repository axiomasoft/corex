<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;
use Flex\Crm\Enums\LeadStatus;
use Flex\Crm\Events\LeadConverted;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Lead;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Support\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Converts a lead into a Contact (+ optional Company, + optional Deal in the
 * given pipeline). Composite use-case: composes the create-* Actions inside one
 * transaction rather than via an orchestration service.
 */
final class ConvertLead
{
    public function __construct(
        private readonly CreateContact $createContact,
        private readonly CreateCompany $createCompany,
        private readonly CreateDeal $createDeal,
        private readonly AssociateContactCompany $associate,
        private readonly ContextGuard $guard,
    ) {}

    public function handle(Lead $lead, ?Pipeline $pipeline = null, bool $withCompany = false): Contact
    {
        $ctx = $this->guard->current();

        if ($lead->getConnection()->getName() !== $ctx->connectionName || ($pipeline !== null && $pipeline->getConnection()->getName() !== $ctx->connectionName)) {
            throw new AuthorizationException('Lead conversion requires the current trusted account connection.');
        }

        return DB::usingConnection($lead->getConnectionName(), fn (): Contact => $lead->getConnection()->transaction(function () use ($lead, $pipeline, $withCompany): Contact {
            $fresh = $lead->newQuery()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();
            $lead->setRawAttributes($fresh->getAttributes(), true);

            if ($lead->status === LeadStatus::Converted) {
                return Contact::on($lead->getConnectionName())->findOrFail($lead->converted_contact_id);
            }

            $company = null;

            if ($withCompany && $lead->company_name !== null && $lead->company_name !== '') {
                $company = $this->createCompany->handle(new CompanyData(name: $lead->company_name, ownerId: $lead->owner_id));
            }

            $contact = $this->createContact->handle(new ContactData(
                nameFirst: $lead->name,
                emails: $lead->email === null || $lead->email === '' ? [] : [['value' => $lead->email]],
                phones: $lead->phone === null || $lead->phone === '' ? [] : [['value' => $lead->phone]],
                ownerId: $lead->owner_id,
            ));

            if ($company !== null) {
                $this->associate->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
            }

            $deal = null;

            if ($pipeline instanceof Pipeline) {
                $deal = $this->createDeal->handle($pipeline, $lead->name, null, $lead->owner_id);
                $deal->contact_id = $contact->id;
                $deal->company_id = $company?->id;
                $deal->save();
            }

            $lead->status = LeadStatus::Converted;
            $lead->converted_contact_id = $contact->id;
            $lead->converted_company_id = $company?->id;
            $lead->converted_deal_id = $deal?->id;
            $lead->converted_at = now();
            $lead->save();

            LeadConverted::dispatch($lead, $contact);

            return $contact;
        }));
    }
}
