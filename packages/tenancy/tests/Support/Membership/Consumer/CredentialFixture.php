<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Membership\Consumer;

use Illuminate\Support\Facades\DB;

/** Disposable tables for the unregistered credential reference consumer. */
final class CredentialFixture
{
    public static function create(): void
    {
        DB::statement('CREATE TABLE membership_credential_sessions (session_id text PRIMARY KEY, account_id uuid NOT NULL, identity_id uuid NOT NULL, operation_id uuid NOT NULL, metadata jsonb NOT NULL)');
        DB::statement('CREATE TABLE membership_credential_remembers (remember_token text PRIMARY KEY, account_id uuid NOT NULL, identity_id uuid NOT NULL, operation_id uuid NOT NULL)');
    }

    public static function drop(): void
    {
        DB::statement('DROP TABLE IF EXISTS membership_credential_remembers');
        DB::statement('DROP TABLE IF EXISTS membership_credential_sessions');
    }
}
