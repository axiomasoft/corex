<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\Outbox;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->assertSuccessful();

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

    expect(DB::connection('pgsql')->table(app(CoreOptions::class)->outboxMessagesTable)->count())->toBe(0);
})->group('pg');
