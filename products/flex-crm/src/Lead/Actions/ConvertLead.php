<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Actions;

use Brick\Money\Money;
use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Deal\Actions\CreateDeal;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Lead\Enums\LeadStatus;
use Flex\Crm\Lead\Events\LeadConverted;
use Flex\Crm\Lead\Models\Lead;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

/**
 * Converts a lead into a Contact (+ optional Company, + optional Deal in the
 * given pipeline). Composite use-case: composes the create-* Actions inside one
 * transaction rather than via an orchestration service.
 */
#[Internal]
final readonly class ConvertLead
{
    public function __construct(
        private CreateContact $createContact,
        private CreateCompany $createCompany,
        private CreateDeal $createDeal,
        private AssociateContactCompany $associate,
        private ContextGuard $guard,
    ) {}

    public function handle(Lead $lead, ?Pipeline $pipeline = null, bool $withCompany = false): Contact
    {
        $ctx = $this->guard->current();

        if ($lead->getConnection()->getName() !== $ctx->connectionName || ($pipeline !== null && $pipeline->getConnection()->getName() !== $ctx->connectionName)) {
            throw new AuthorizationException('Lead conversion requires the current trusted account connection.');
        }

        return DB::usingConnection($ctx->connectionName, fn (): Contact => $lead->getConnection()->transaction(function () use ($lead, $pipeline, $withCompany): Contact {
            $fresh = $lead->newQuery()->whereKey($lead->getKey())->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->firstOrFail();
            $this->guard->current(lock: true);
            $lead->setRawAttributes($fresh->getAttributes(), true);

            if ($lead->status === LeadStatus::Converted) {
                return Contact::on($lead->getConnectionName())->findOrFail($lead->converted_contact_id);
            }

            if ($lead->status !== LeadStatus::Open || (($lead->contact_name === null || trim($lead->contact_name) === '') && ($lead->email === null || $lead->email === '') && ($lead->phone === null || $lead->phone === ''))) {
                throw ValidationException::withMessages(['lead' => 'Conversion requires an open lead and explicit contact identity.']);
            }

            $company = null;

            if ($withCompany && $lead->company_name !== null && $lead->company_name !== '') {
                $company = $this->createCompany->handle(new CompanyData(name: $lead->company_name, ownerId: $lead->owner_id, sourceId: $lead->source_id));
            }

            $contact = $this->createContact->handle(new ContactData(
                nameFirst: $lead->contact_name,
                emails: $lead->email === null || $lead->email === '' ? [] : [['value' => $lead->email]],
                phones: $lead->phone === null || $lead->phone === '' ? [] : [['value' => $lead->phone]],
                ownerId: $lead->owner_id,
                sourceId: $lead->source_id,
            ));

            if ($company !== null) {
                $this->associate->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
            }

            $deal = null;

            if ($pipeline instanceof Pipeline) {
                $deal = $this->createDeal->handle(pipeline: $pipeline, title: $lead->title, amount: $lead->amount === null ? null : Money::ofMinor($lead->amount, $lead->currency), ownerId: $lead->owner_id);
                $deal->company_id = $company?->id;
                $deal->source_id = $lead->source_id;

                if (! $deal->save()) {
                    throw ValidationException::withMessages(['deal' => 'Deal update was vetoed.']);
                }
                $values = ['deal_id' => $deal->id, 'contact_id' => $contact->id, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()];

                $values['id'] = $deal->newUniqueId();
                $deal->getConnection()->table('crm_deal_contacts')->insert($values);
            }

            $lead->status = LeadStatus::Converted;
            $lead->converted_contact_id = $contact->id;
            $lead->converted_company_id = $company?->id;
            $lead->converted_deal_id = $deal?->id;
            $lead->converted_at = now();

            if (! $lead->save()) {
                throw ValidationException::withMessages(['lead' => 'Lead conversion was vetoed.']);
            }

            event(new LeadConverted($lead, $contact));

            return $contact;
        }));
    }
}
