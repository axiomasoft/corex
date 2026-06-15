<?php

declare(strict_types=1);

namespace CoreX\Crm\Actions;

use CoreX\Crm\Events\ContactCreated;
use CoreX\Crm\Models\Contact;
use Illuminate\Support\Facades\DB;

final class CreateContact
{
    /** @param array<string, mixed>|null $customFields */
    public function handle(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $jobTitle = null,
        ?string $companyId = null,
        ?string $ownerId = null,
        ?array $customFields = null,
    ): Contact {
        return DB::transaction(function () use ($firstName, $lastName, $email, $phone, $jobTitle, $companyId, $ownerId, $customFields): Contact {
            $contact = Contact::query()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'job_title' => $jobTitle,
                'company_id' => $companyId,
                'owner_id' => $ownerId,
                'custom_fields' => $customFields,
            ]);

            ContactCreated::dispatch($contact);

            return $contact;
        });
    }
}
