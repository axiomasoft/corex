<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Events\ActivityLogged;
use Flex\Crm\Models\Activity;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Lead;
use Flex\Crm\Support\ContextGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class LogActivity
{
    public function __construct(private readonly ContextGuard $guard) {}

    /** @param array<string, mixed>|null $metadata Legacy metadata has no canonical conversion. */
    public function handle(
        Model $subject,
        ActivityType $type,
        string $summary,
        ?string $body = null,
        ?string $ownerId = null,
        ?array $metadata = null,
    ): Activity {
        $ctx = $this->guard->current();

        if ($metadata !== null) {
            throw ValidationException::withMessages(['metadata' => 'Legacy metadata requires explicit canonical fields; automatic conversion is unsupported.']);
        }

        if (! ($subject instanceof Company || $subject instanceof Contact || $subject instanceof Lead || $subject instanceof Deal) || $subject->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('Activity target requires the current trusted account connection and CRM entity.');
        }

        return $this->guard->connection($ctx)->transaction(function () use ($subject, $type, $summary, $body, $ownerId, $ctx): Activity {
            $this->guard->current(lock: true);
            $subject = $subject->newQuery()->whereKey($subject->getKey())->sharedLock()->firstOrFail();
            $activity = new Activity([
                'workspace_id' => $subject->getAttribute('workspace_id'),
                'kind' => $type,
                'status' => 'done',
                'subject' => $summary,
                'body' => $body,
                'owner_id' => $ownerId,
            ]);
            $activity->setConnection($ctx->connectionName);

            if (! $activity->save()) {
                throw ValidationException::withMessages(['activity' => 'Activity creation was vetoed.']);
            }
            $activity->getConnection()->table('crm_activity_targets')->insert(['activity_id' => $activity->id, 'entity_type' => $subject->getMorphClass(), 'entity_id' => $subject->getKey(), 'created_at' => now()]);

            ActivityLogged::dispatch($activity);

            return $activity;
        });
    }
}
