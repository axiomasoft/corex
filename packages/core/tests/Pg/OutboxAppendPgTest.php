<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');

    outboxAppendBindRegistry(outboxAppendDeclaredContract());
});

it('rolls back an outbox row with the business transaction', function (): void {
    $outbox = app(Outbox::class);

    try {
        DB::connection('pgsql')->transaction(function () use ($outbox): void {
            $outbox->append(messagingSampleEnvelope(['messageId' => '01932f5a-0000-7000-8000-000000000099']));

            throw new RuntimeException('force-rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force-rollback');
    }

    expect(DB::connection('pgsql')->table('sys_outbox_messages')->count())->toBe(0);
})->group('pg');
