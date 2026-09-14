<?php

declare(strict_types=1);

use Flex\Crm\Actions\ConvertLead;
use Flex\Crm\Actions\CreateLead;
use Flex\Crm\Enums\LeadSource;
use Flex\Crm\Events\LeadConverted;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Lead;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses()->group('pg');

it('serializes concurrent conversion and returns the committed result on retry', function (): void {
    config(['database.default' => 'pgsql', 'database.connections.conversion_race' => config('database.connections.pgsql')]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true, '--force' => true])->assertSuccessful();
    Event::fake([LeadConverted::class]);
    $lead = app(CreateLead::class)->handle('Once', LeadSource::Website);
    $other = Lead::on('conversion_race')->findOrFail($lead->id);
    DB::connection('conversion_race')->beginTransaction();
    DB::connection('conversion_race')->statement("SET LOCAL lock_timeout = '50ms'");
    $observed = false;
    DB::listen(function ($query) use (&$observed, $other): void {
        if ($observed || $query->connectionName !== 'pgsql' || ! str_contains($query->sql, '"crm_leads"') || ! str_contains($query->sql, 'for update')) {
            return;
        }
        $observed = true;

        try {
            app(ConvertLead::class)->handle($other);
            test()->fail('Concurrent conversion did not serialize.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('55P03');
        }
    });

    try {
        $first = app(ConvertLead::class)->handle($lead);
        $retry = app(ConvertLead::class)->handle($other);
        expect($observed)->toBeTrue()->and($retry->id)->toBe($first->id)->and(Contact::query()->count())->toBe(1);
        Event::assertDispatchedTimes(LeadConverted::class, 1);
    } finally {
        DB::connection('conversion_race')->rollBack();
        DB::purge('conversion_race');
    }
});
