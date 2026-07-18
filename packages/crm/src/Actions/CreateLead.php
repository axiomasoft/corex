<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Enums\LeadSource;
use Flex\Crm\Enums\LeadStatus;
use Flex\Crm\Events\LeadCreated;
use Flex\Crm\Models\Lead;
use Illuminate\Support\Facades\DB;

final class CreateLead
{
    /** @param array<string, mixed>|null $customFields */
    public function handle(
        string $name,
        LeadSource $source,
        ?string $email = null,
        ?string $phone = null,
        ?string $companyName = null,
        ?string $ownerId = null,
        ?array $customFields = null,
    ): Lead {
        return DB::transaction(function () use ($name, $source, $email, $phone, $companyName, $ownerId, $customFields): Lead {
            $lead = Lead::query()->create([
                'name' => $name,
                'source' => $source,
                'status' => LeadStatus::New,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $companyName,
                'owner_id' => $ownerId,
                'custom_fields' => $customFields,
            ]);

            LeadCreated::dispatch($lead);

            return $lead;
        });
    }
}
