<?php

declare(strict_types=1);

use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\Data\ExecutionContext;
use Flex\Crm\Lead\Actions\ConvertLead;
use Flex\Crm\Lead\Actions\CreateLead;
use Flex\Crm\Lead\Events\LeadConverted;
use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\TestContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses()->group('pg');

it('serializes concurrent conversion and returns the committed result on retry', function (): void {
    config(['database.default' => 'pgsql', 'database.connections.conversion_race' => config('database.connections.pgsql')]);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
    Event::fake([LeadConverted::class]);
    $fixture = TestContext::seed('pgsql');
    $original = $fixture->current();
    $lead = app(CreateLead::class)->handle(title: 'Once', contactName: 'Jane');
    $other = clone $lead;
    $other->setConnection('conversion_race');
    DB::connection('conversion_race')->beginTransaction();
    DB::connection('conversion_race')->statement("SET LOCAL lock_timeout = '50ms'");
    $observed = false;
    DB::listen(function ($query) use (&$observed, $other, $fixture, $original): void {
        if ($observed || $query->connectionName !== 'pgsql' || ! str_contains($query->sql, '"crm_leads"') || ! str_contains($query->sql, 'for update')) {
            return;
        }
        $observed = true;

        try {
            $fixture->context = new ExecutionContext(tenant: $original->tenant, connectionName: 'conversion_race', actor: $original->actor, principalId: $original->principalId);
            app(ConvertLead::class)->handle($other);
            test()->fail('Concurrent conversion did not serialize.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('55P03');
        } finally {
            $fixture->context = $original;
        }
    });

    try {
        $first = app(ConvertLead::class)->handle($lead);
        $fixture->context = new ExecutionContext(tenant: $original->tenant, connectionName: 'conversion_race', actor: $original->actor, principalId: $original->principalId);
        $retry = app(ConvertLead::class)->handle($other);
        expect($observed)->toBeTrue()->and($retry->id)->toBe($first->id)->and(Contact::on('conversion_race')->count())->toBe(1);
        Event::assertDispatchedTimes(LeadConverted::class, 1);
    } finally {
        DB::connection('conversion_race')->rollBack();
        DB::purge('conversion_race');
    }
});
