<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Enums\LeadStatus;
use Flex\Crm\Events\LeadConverted;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Lead;
use Flex\Crm\Models\Pipeline;
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
    ) {}

    public function handle(Lead $lead, ?Pipeline $pipeline = null, bool $withCompany = false): Contact
    {
        return DB::transaction(function () use ($lead, $pipeline, $withCompany): Contact {
            $company = null;

            if ($withCompany && $lead->company_name !== null && $lead->company_name !== '') {
                $company = $this->createCompany->handle(name: $lead->company_name, ownerId: $lead->owner_id);
            }

            $contact = $this->createContact->handle(
                firstName: $lead->name,
                email: $lead->email,
                phone: $lead->phone,
                companyId: $company?->id,
                ownerId: $lead->owner_id,
            );

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
        });
    }
}
