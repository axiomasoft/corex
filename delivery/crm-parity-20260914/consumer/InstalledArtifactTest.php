<?php

declare(strict_types=1);

use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\AccountConnectionResolver;
use CoreX\Tenancy\DatabaseConnectionConfig;
use Flex\Crm\Actions\AssociateContactCompany;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;
use Flex\Crm\Data\ExecutionContext;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\TestContext;
use Flex\Crm\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class InstalledArtifactTest extends TestCase
{
    public function test_installed_archive_discovery_lifecycle_and_create_scenario(): void
    {
        $context = $this->prepareInstalledAccount();
        $lifecycle = app(ModuleLifecycle::class);
        $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Installed archive'));
        $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane', nameLast: 'Doe'));
        $link = app(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
        self::assertTrue($link->is_primary);
        self::assertSame('Doe Jane', Contact::query()->with('companies')->findOrFail($contact->id)->display_name);
        self::assertSame($context->current()->actor->toArray(), $company->created_by);
        $migrations = DB::table('migrations')->count();
        $lifecycle->disable('flex/crm', DisableReason::Manual);

        try {
            Company::query()->count();
            self::fail('Disabled CRM must reject scoped reads.');
        } catch (AuthorizationException) {
            self::assertSame(1, DB::table('crm_companies')->count());
        }
        $lifecycle->enable('flex/crm');
        $lifecycle->enable('flex/crm');
        self::assertSame($migrations, DB::table('migrations')->count());
        self::assertSame($company->id, Company::query()->sole()->id);
    }

    public function test_rejects_actual_foreign_account_references_and_misdirected_pdo(): void
    {
        $fixture = $this->prepareInstalledAccount();
        $original = $fixture->current();
        $expected = $fixture->resolve($original->tenant->account);
        config(['database.connections.foreign' => [...config('database.connections.pgsql'), 'database' => 'corex_crm_foreign_test']]);
        $foreign = DB::connection('foreign');
        self::assertSame('corex_crm_foreign_test', $foreign->selectOne('SELECT current_database() AS name')->name);
        $foreign->statement('DROP SCHEMA IF EXISTS public CASCADE');
        $foreign->statement('CREATE SCHEMA public');
        config(['database.default' => 'foreign']);
        AccountFixture::migrate();
        $owner = (string) Str::uuid7();
        $source = (string) Str::uuid7();
        $foreign->table('users')->insert(['id' => $owner, 'name' => 'Foreign owner']);
        $foreign->statement('CREATE TABLE crm_sources (id uuid PRIMARY KEY)');
        $foreign->table('crm_sources')->insert(['id' => $source]);
        config(['database.default' => 'pgsql']);
        foreach ([new CompanyData(name: 'Foreign owner', ownerId: $owner), new CompanyData(name: 'Foreign source', sourceId: $source)] as $data) {
            try {
                app(CreateCompany::class)->handle($data);
                self::fail('UUID existing only in another physical account must reject.');
            } catch (AuthorizationException) {
                self::assertSame(0, DB::table('crm_companies')->count());
            }
        }
        app()->instance(AccountConnectionResolver::class, new class($expected) implements AccountConnectionResolver
        {
            public function __construct(private DatabaseConnectionConfig $expected) {}

            public function resolve(AccountRef $account): DatabaseConnectionConfig
            {
                return $this->expected;
            }
        });
        $fixture->context = new ExecutionContext(tenant: $original->tenant, connectionName: 'foreign', actor: $original->actor, principalId: $original->principalId);

        try {
            app(CreateCompany::class)->handle(new CompanyData(name: 'Misdirected account'));
            self::fail('A foreign physical database must reject.');
        } catch (AuthorizationException) {
            self::assertSame(0, DB::connection('pgsql')->table('crm_companies')->count());
        }
        // A stale/forged configuration must not disguise an already-connected foreign PDO.
        $fixture->context = $original;
        DB::connection('pgsql')->setPdo($foreign->getPdo())->setReadPdo($foreign->getPdo());
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('active PDO database differs');
        app(CreateCompany::class)->handle(new CompanyData(name: 'Forged configured database'));
    }

    private function prepareInstalledAccount(): TestContext
    {
        $loaded = (new ReflectionClass(CreateCompany::class))->getFileName();
        self::assertStringStartsWith(__DIR__.'/vendor/flex/crm/', $loaded);
        self::assertFalse(is_link(__DIR__.'/vendor/flex/crm'));
        config(['database.default' => 'pgsql', 'corex-modules.connection' => 'pgsql', 'corex.ids.strategy' => 'uuid7']);
        DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
        DB::statement('CREATE SCHEMA public');
        AccountFixture::migrate();
        self::assertFalse(Schema::hasTable('crm_companies')); // Provider startup never runs CRM DDL.
        $databasePath = database_path();
        $bootstrapPath = app()->bootstrapPath();
        app()->setBasePath(__DIR__);
        app()->useDatabasePath($databasePath);
        app()->useBootstrapPath($bootstrapPath);
        $discovered = (new ModuleDiscovery)->discover();
        self::assertCount(1, $discovered);
        self::assertSame('flex/crm', $discovered[0]->composerName);
        $this->artisan('migrate', ['--path' => __DIR__.'/vendor/corex/modules/database/migrations', '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->artisan('corex:modules:compile')->assertSuccessful();
        app()->forgetInstance(ModuleRegistry::class);
        app()->forgetInstance(ModuleLifecycle::class);
        $context = TestContext::seed('pgsql'); // Test-only trust adapter, never production code.
        app()->forgetInstance(ModuleRegistry::class); // Use the actual installed registry, not the fixture mock.
        $lifecycle = app(ModuleLifecycle::class);
        $lifecycle->enable('flex/crm');
        foreach (['crm_sources', 'crm_companies', 'crm_contacts', 'crm_contact_company', 'crm_pipelines', 'crm_stages', 'crm_deals', 'crm_leads', 'crm_activities'] as $table) {
            self::assertTrue(Schema::hasTable($table), $table);
        }

        return $context;
    }
}
