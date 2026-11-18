<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Actions;

use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Lead\Enums\LeadStatus;
use Flex\Crm\Lead\Events\LeadCreated;
use Flex\Crm\Lead\Models\Lead;
use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CreateLead
{
    public function __construct(private ContextGuard $guard) {}

    public function handle(
        string $title,
        ?string $contactName = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $companyName = null,
        ?string $ownerId = null,
        string|int|null $sourceId = null,
    ): Lead {
        $ctx = $this->guard->current();

        return $this->guard->connection($ctx)->transaction(function () use ($title, $contactName, $sourceId, $email, $phone, $companyName, $ownerId, $ctx): Lead {
            $this->guard->current(lock: true);
            $lead = Lead::on($ctx->connectionName)->create([
                'workspace_id' => $this->guard->workspace(ctx: $ctx, accountLevel: false),
                'title' => $title,
                'contact_name' => $contactName,
                'source_id' => $sourceId,
                'status' => LeadStatus::Open,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $companyName,
                'owner_id' => $ownerId,
            ]);

            if (! $lead->exists) {
                throw ValidationException::withMessages(['lead' => 'Lead creation was vetoed.']);
            }

            event(new LeadCreated($lead));

            return $lead;
        });
    }
}
