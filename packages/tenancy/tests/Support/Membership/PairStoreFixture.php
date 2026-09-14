<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership;

use Illuminate\Support\Facades\DB;

/** Disposable physical mapping for the internal pair-store reference only. */
final class PairStoreFixture
{
    public static function create(): void
    {
        DB::statement('CREATE TABLE membership_pair_references (account_id uuid NOT NULL, identity_id uuid NOT NULL, revision numeric(20, 0) NOT NULL DEFAULT 0 CHECK (revision >= 0 AND revision <= 18446744073709551615), revoke_generation numeric(20, 0) NOT NULL DEFAULT 0 CHECK (revoke_generation >= 0 AND revoke_generation <= 18446744073709551615), local_epoch numeric(20, 0) NOT NULL DEFAULT 0 CHECK (local_epoch >= 0 AND local_epoch <= 18446744073709551615), operation_fence numeric(20, 0) NOT NULL DEFAULT 0 CHECK (operation_fence >= 0 AND operation_fence <= 18446744073709551615), application_fence numeric(20, 0) NULL CHECK (application_fence >= 0 AND application_fence <= 18446744073709551615), operation_id uuid NULL, applied_operation_id uuid NULL, barrier_id uuid NULL, denied boolean NOT NULL DEFAULT false, PRIMARY KEY (account_id, identity_id))');
    }

    public static function drop(): void
    {
        DB::statement('DROP TABLE IF EXISTS membership_pair_references');
    }
}
