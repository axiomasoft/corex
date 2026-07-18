<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Enums\ActivityType;
use Flex\Crm\Events\ActivityLogged;
use Flex\Crm\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class LogActivity
{
    /** @param array<string, mixed>|null $metadata */
    public function handle(
        Model $subject,
        ActivityType $type,
        string $summary,
        ?string $body = null,
        ?string $userId = null,
        ?array $metadata = null,
    ): Activity {
        return DB::transaction(function () use ($subject, $type, $summary, $body, $userId, $metadata): Activity {
            $activity = new Activity([
                'type' => $type,
                'summary' => $summary,
                'body' => $body,
                'user_id' => $userId,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
            $activity->subject()->associate($subject);
            $activity->save();

            ActivityLogged::dispatch($activity);

            return $activity;
        });
    }
}
