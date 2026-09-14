<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership\Consumer;

use Illuminate\Support\Facades\DB;

/** Disposable durable inbox mapping for local reference tests only. */
final class InboxFixture
{
    public static function create(): void
    {
        DB::statement("CREATE TABLE membership_trigger_inbox (event_id uuid PRIMARY KEY, logical_digest text NOT NULL, delivery_id uuid NOT NULL, status text NOT NULL CHECK (status IN ('pending', 'acknowledged')), owner_id text NULL, lease_until bigint NULL, attempts integer NOT NULL)");
    }

    public static function drop(): void
    {
        DB::statement('DROP TABLE IF EXISTS membership_trigger_inbox');
    }
}
