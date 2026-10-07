<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Company\Events\CompanyCreated;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;
use Flex\Crm\Contact\Events\ContactCompanyAssociated;
use Flex\Crm\Contact\Events\ContactCreated;
use Flex\Crm\Context\Data\Actor;
use Flex\Crm\Context\Data\ExecutionContext;
use Flex\Crm\Context\Models\Source;
use Flex\Crm\Tests\Support\AccountFixture;
use Flex\Crm\Tests\Support\TestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql']);
    app()->forgetInstance(CoreOptions::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    AccountFixture::migrate();
    $this->migrateSchemaSets(['flex/crm:tenant'], DB::getDefaultConnection());
    $this->crmContext = TestContext::seed('pgsql');
});

it('matches approved PostgreSQL fields lengths defaults indexes and foreign keys', function (): void {
    $columns = collect(DB::select("SELECT table_name,column_name,data_type,character_maximum_length,is_nullable,column_default FROM information_schema.columns WHERE table_schema='public' AND table_name IN ('crm_companies','crm_contacts','crm_contact_company','crm_sources')"))->groupBy('table_name');
    $expected = [
        'crm_companies' => ['id', 'workspace_id', 'name', 'legal_name', 'domain', 'inn', 'kpp', 'ogrn', 'phones', 'emails', 'address', 'bank_details', 'source_id', 'owner_id', 'created_by', 'search', 'created_at', 'updated_at', 'deleted_at'],
        'crm_contacts' => ['id', 'workspace_id', 'name_last', 'name_first', 'name_middle', 'display_name', 'job_title', 'phones', 'emails', 'birthday', 'source_id', 'owner_id', 'created_by', 'search', 'created_at', 'updated_at', 'deleted_at'],
        'crm_contact_company' => ['id', 'contact_id', 'company_id', 'label', 'is_primary', 'created_at', 'updated_at'],
        'crm_sources' => ['id', 'workspace_id', 'code', 'name', 'is_active', 'position', 'created_at', 'updated_at'],
    ];
    foreach ($expected as $table => $names) {
        expect($columns[$table]->pluck('column_name')->all())->toEqualCanonicalizing($names);
        $map = $columns[$table]->keyBy('column_name');
        expect($map['id']->data_type)->toBe('uuid')->and($map['created_at']->data_type)->toBe('timestamp with time zone')
            ->and($map['created_at']->is_nullable)->toBe('NO')->and($map['created_at']->column_default)->toBe('CURRENT_TIMESTAMP');

        if (isset($map['phones'])) {
            expect($map['phones']->data_type)->toBe('jsonb')->and($map['phones']->column_default)->toBe("'[]'::jsonb")
                ->and($map['created_by']->is_nullable)->toBe('NO')->and($map['search']->data_type)->toBe('tsvector')
                ->and($map['workspace_id']->is_nullable)->toBe('YES');
        }
    }
    foreach (['domain' => 190, 'inn' => 12, 'kpp' => 9, 'ogrn' => 15] as $field => $length) {
        expect($columns['crm_companies']->keyBy('column_name')[$field]->character_maximum_length)->toBe($length);
    }
    foreach (['name_first' => 120, 'name_last' => 120, 'name_middle' => 120, 'display_name' => 255, 'job_title' => 190] as $field => $length) {
        expect($columns['crm_contacts']->keyBy('column_name')[$field]->character_maximum_length)->toBe($length);
    }
    $indexes = collect(DB::select("SELECT indexname,indexdef FROM pg_indexes WHERE schemaname='public'"))->keyBy('indexname');
    foreach (['crm_companies', 'crm_contacts'] as $table) {
        expect($indexes[$table.'_search_ix']->indexdef)->toContain('USING gin (search)')
            ->and($indexes[$table.'_phones_ix']->indexdef)->toContain('jsonb_path_ops')
            ->and($indexes[$table.'_emails_ix']->indexdef)->toContain('jsonb_path_ops')
            ->and($indexes[$table.'_ws_ix']->indexdef)->toContain('WHERE (deleted_at IS NULL)')
            ->and($indexes[$table.'_owner_ix']->indexdef)->toContain('WHERE (deleted_at IS NULL)');
    }
    expect($indexes['crm_companies_inn_ix']->indexdef)->not->toContain('UNIQUE')
        ->and($indexes['crm_companies_inn_ix']->indexdef)->toContain('inn IS NOT NULL')
        ->and($indexes['crm_contact_company_primary_uq']->indexdef)->toContain('UNIQUE', '(contact_id)', 'WHERE is_primary')
        ->and($indexes['crm_contact_company_company_ix']->indexdef)->toContain('(company_id)')
        ->and($indexes['crm_sources_workspace_id_code_uq']->indexdef)->toContain('NULLS NOT DISTINCT');
    $fks = collect(DB::select("SELECT conrelid::regclass::text AS tbl,pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE contype='f'"));
    foreach (['crm_companies', 'crm_contacts'] as $table) {
        $defs = $fks->where('tbl', $table)->pluck('def')->implode(' ');
        expect($defs)->toContain('FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL', 'FOREIGN KEY (source_id) REFERENCES crm_sources(id) ON DELETE SET NULL', 'FOREIGN KEY (workspace_id) REFERENCES wsp_workspaces(id) ON DELETE CASCADE');
    }
    expect($fks->where('tbl', 'crm_contact_company')->pluck('def')->implode(' '))->toContain('REFERENCES crm_contacts(id) ON DELETE CASCADE', 'REFERENCES crm_companies(id) ON DELETE CASCADE');
});

it('roundtrips all approved company and contact values and search', function (): void {
    $ctx = $this->crmContext->current();
    $source = Source::create(['workspace_id' => $ctx->tenant->workspace->id, 'code' => 'web', 'name' => ['ru' => 'Сайт']]);
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: ' Компания ', legalName: 'ООО Компания', domain: 'example.test', inn: '123456789012', kpp: '123456789', ogrn: '123456789012345', phones: [['value' => '+1 (650) 253-0000', 'type' => 'work', 'is_primary' => true]], emails: [['value' => 'USER@example.test']], address: ['city' => 'Москва'], bankDetails: ['bik' => '044525225'], sourceId: $source->id, ownerId: $ctx->principalId))->fresh();
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: '  Иван  ', nameLast: 'Иванов', nameMiddle: 'Иванович', jobTitle: str_repeat('a', 190), birthday: '2000-02-29', phones: [['value' => '+16502530000']], emails: [['value' => 'C@example.test']], sourceId: $source->id, ownerId: $ctx->principalId))->fresh();
    expect($company->name)->toBe('Компания')->and($company->inn)->toBe('123456789012')->and($company->kpp)->toBe('123456789')->and($company->ogrn)->toBe('123456789012345')
        ->and($company->domain)->toBe('example.test')->and($company->phones[0]['norm'])->toBe('+16502530000')->and($company->address)->toBe(['city' => 'Москва'])
        ->and($company->bank_details)->toBe(['bik' => '044525225'])->and($company->owner_id)->toBe($ctx->principalId)->and($company->source_id)->toBe($source->id)
        ->and($contact->display_name)->toBe('Иванов Иван Иванович')->and($contact->birthday->format('Y-m-d'))->toBe('2000-02-29')
        ->and($contact->created_by)->toEqual($ctx->actor->toArray())->and($company->id[14])->toBe('7');
    expect(DB::selectOne('SELECT search @@ plainto_tsquery(\'russian\', ?) AS found FROM crm_companies WHERE id=?', ['Компания', $company->id])->found)->toBeTrue();
    $duplicate = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Duplicate', inn: '123456789012'));
    expect($duplicate->fresh()->phones)->toBe([])->and($duplicate->emails)->toBe([]);
});

it('uses deterministic channel fallback and nullable whitespace components', function (): void {
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: ' ', nameMiddle: ' ', emails: [['value' => 'b@example.test'], ['value' => 'A@example.test', 'is_primary' => true]]));
    expect($contact->name_first)->toBeNull()->and($contact->display_name)->toBe('a@example.test');
    $phone = resolve(CreateContact::class)->handle(new ContactData(phones: [['value' => '+1 650 253 0000']]));
    expect($phone->display_name)->toBe('+16502530000');
    $long = resolve(CreateContact::class)->handle(new ContactData(nameFirst: str_repeat('a', 120), nameLast: str_repeat('b', 120), nameMiddle: str_repeat('c', 120)));
    expect(mb_strlen($long->display_name))->toBe(255);
});

it('rejects malformed channels without storing a contact', function (array $channels, bool $phone): void {
    expect(fn () => resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Valid', phones: $phone ? $channels : [], emails: $phone ? [] : $channels)))->toThrow(ValidationException::class);
    expect(DB::table('crm_contacts')->count())->toBe(0);
})->with([
    [[['value' => '']], false], [[['value' => 'invalid']], false], [[['value' => 'a@example.test'], ['value' => 'A@example.test']], false],
    [[['value' => 'a@example.test', 'is_primary' => true], ['value' => 'b@example.test', 'is_primary' => true]], false],
    [[['value' => 'a@example.test', 'is_primary' => 'true']], false], [[['value' => 'a@example.test', 'unexpected' => 1]], false],
    [[['value' => '6502530000']], true], [[['value' => '+123']], true], [[['value' => '+16502530000 ext 1']], true],
    [[['value' => '+16502530000'], ['value' => '+1 650 253 0000']], true], [['value' => 'a@example.test'], false],
]);

it('rejects invalid dates and field widths', function (): void {
    expect(fn () => resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Valid', birthday: '2023-02-29')))->toThrow(ValidationException::class);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Valid', inn: str_repeat('1', 13))))->toThrow(ValidationException::class);
    expect(fn () => resolve(CreateContact::class)->handle(new ContactData(nameFirst: str_repeat('a', 121))))->toThrow(ValidationException::class);
});

it('fails closed for missing context and revoked membership on reads and writes', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Visible'));
    $ctx = $this->crmContext->current();
    DB::table('wsp_workspace_members')->where('user_id', $ctx->principalId)->update(['status' => 'revoked']);
    expect(fn () => Company::find($company->id))->toThrow(AuthorizationException::class);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Denied')))->toThrow(AuthorizationException::class);
    $this->crmContext->context = null;
    expect(fn () => Company::count())->toThrow(AuthorizationException::class);
});

it('separates NULL account data current workspace and foreign workspaces', function (): void {
    $ctx = $this->crmContext->current();
    $local = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Local'));
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Denied NULL', accountLevel: true)))->toThrow(AuthorizationException::class);
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'pgsql', actor: $ctx->actor, principalId: $ctx->principalId, allowAccountLevel: true);
    $global = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Global', accountLevel: true));
    $this->crmContext->context = $ctx;
    expect(Company::pluck('id')->all())->toBe([$local->id]);
    $ws = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $ws, 'slug' => 'other']);
    DB::table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $ws, 'user_id' => $ctx->principalId, 'status' => 'active']);
    $otherTenant = new TenantContext($ctx->tenant->account, new WorkspaceRef(id: $ws, slug: 'other', type: 'main', path: '', parentId: null));
    $this->crmContext->context = new ExecutionContext(tenant: $otherTenant, connectionName: 'pgsql', actor: $ctx->actor, principalId: $ctx->principalId);
    expect(Company::count())->toBe(0);
    expect(fn () => resolve(AssociateContactCompany::class)->handle(contactId: (string) Str::uuid7(), companyId: $local->id))->toThrow(ModelNotFoundException::class);
    $this->crmContext->context = new ExecutionContext(tenant: new TenantContext($ctx->tenant->account), connectionName: 'pgsql', actor: $ctx->actor, principalId: $ctx->principalId, allowAccountLevel: true);
    expect(Company::pluck('id')->all())->toBe([$global->id]);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Missing workspace')))->toThrow(AuthorizationException::class);
});

it('rejects associations between NULL and workspace even with account capability', function (): void {
    $ctx = $this->crmContext->current();
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'pgsql', actor: $ctx->actor, principalId: $ctx->principalId, allowAccountLevel: true);
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Global', accountLevel: true));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Local'));
    expect(fn () => resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id))->toThrow(AuthorizationException::class);
});

it('rejects foreign or inactive source and owner references', function (): void {
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Foreign', ownerId: (string) Str::uuid7())))->toThrow(AuthorizationException::class);
    expect(fn () => resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Foreign', sourceId: (string) Str::uuid7())))->toThrow(AuthorizationException::class);
    $owner = (string) Str::uuid7();
    DB::table('users')->insert(['id' => $owner, 'name' => 'No membership']);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Foreign', ownerId: $owner)))->toThrow(AuthorizationException::class);
    $ws = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $ws, 'slug' => 'foreign']);
    $source = Source::create(['workspace_id' => $ws, 'code' => 'foreign', 'name' => ['ru' => 'Foreign']]);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Foreign', sourceId: $source->id)))->toThrow(AuthorizationException::class);
});

it('rejects forged actors and mismatched account connections', function (): void {
    $ctx = $this->crmContext->current();
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'pgsql', actor: new Actor(source: 'user', id: (string) Str::uuid7(), name: 'Forged'), principalId: $ctx->principalId);
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Forgery')))->toThrow(AuthorizationException::class);
    $this->crmContext->context = $ctx;
    $resolver = Mockery::mock(AccountConnectionResolver::class);
    $resolver->shouldReceive('resolve')->andReturn(new DatabaseConnectionConfig(driver: 'pgsql', host: '127.0.0.1', port: 5434, database: 'foreign_account_test', username: 'corex'));
    app()->instance(AccountConnectionResolver::class, $resolver);
    expect(fn () => Company::count())->toThrow(AuthorizationException::class);
    expect(DB::table('crm_companies')->count())->toBe(0);
});

it('stores each admitted B13 actor kind through a trusted run-as context', function (string $source): void {
    $ctx = $this->crmContext->current();
    $actor = new Actor(source: $source, id: $source === 'user' ? (string) $ctx->principalId : 'verified-'.$source, name: 'Verified '.$source);
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'pgsql', actor: $actor, principalId: $ctx->principalId);
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Actor'));
    expect($company->fresh()->created_by)->toEqual($actor->toArray());
})->with(['user', 'agent', 'api', 'workflow', 'import', 'system']);

it('dispatches mutation events only after the successful outer commit', function (): void {
    $events = [];
    foreach ([CompanyCreated::class, ContactCreated::class, ContactCompanyAssociated::class] as $event) {
        Event::listen($event, function ($value) use (&$events): void {
            $events[] = $value::class;
        });
    }
    $create = function () use (&$events): void {
        $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Commit'));
        $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Commit'));
        resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);
        expect($events)->toBe([]);
    };
    expect(fn () => DB::transaction(function () use ($create): void {
        $create();

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);
    expect($events)->toBe([])->and(DB::table('crm_companies')->count())->toBe(0);
    DB::transaction($create);
    expect($events)->toBe([CompanyCreated::class, ContactCreated::class, ContactCompanyAssociated::class]);
});

it('keeps committed data when a synchronous after-commit listener fails', function (): void {
    Event::listen(CompanyCreated::class, static function (): void {
        throw new RuntimeException('listener');
    });
    expect(fn () => resolve(CreateCompany::class)->handle(new CompanyData(name: 'Committed')))->toThrow(RuntimeException::class, 'listener');
    expect(DB::table('crm_companies')->where('name', 'Committed')->count())->toBe(1);
});

it('preserves pivot on soft delete and applies hard delete SET NULL and CASCADE', function (): void {
    $ctx = $this->crmContext->current();
    $source = Source::create(['code' => 'account', 'name' => ['ru' => 'Account']]);
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Company', sourceId: $source->id, ownerId: $ctx->principalId));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Contact', sourceId: $source->id, ownerId: $ctx->principalId));
    resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);
    $company->delete();
    expect(DB::table('crm_contact_company')->count())->toBe(1)->and($contact->companies()->count())->toBe(0);
    DB::table('crm_sources')->where('id', $source->id)->delete();
    expect(DB::table('crm_contacts')->value('source_id'))->toBeNull();
    DB::table('users')->where('id', $ctx->principalId)->delete();
    expect(DB::table('crm_contacts')->value('owner_id'))->toBeNull()->and(DB::table('crm_companies')->value('owner_id'))->toBeNull();
    DB::table('crm_companies')->where('id', $company->id)->delete();
    expect(DB::table('crm_contact_company')->count())->toBe(0);
    DB::table('wsp_workspaces')->where('id', $ctx->tenant->workspace->id)->delete();
    expect(DB::table('crm_contacts')->count())->toBe(0);
});

it('enforces pair and primary constraints for direct database writers', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'One'));
    $other = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Two'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Contact'));
    $action = resolve(AssociateContactCompany::class);
    $action->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
    expect(fn () => $action->handle(contactId: $contact->id, companyId: $company->id))->toThrow(ValidationException::class);
    expect(fn () => $action->handle(contactId: $contact->id, companyId: $other->id, isPrimary: true))->toThrow(ValidationException::class);
    foreach ([$company->id, $other->id] as $id) {
        try {
            DB::table('crm_contact_company')->insert(['id' => (string) Str::uuid7(), 'contact_id' => $contact->id, 'company_id' => $id, 'is_primary' => true]);
            test()->fail('Unique constraint not enforced.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('23505');
        }
    }
});

it('serializes competing primary actions and rejects the loser after commit', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Winner'));
    $other = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Loser'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Contact'));
    $ctx = $this->crmContext->current();
    config(['database.connections.primary_race' => config('database.connections.pgsql')]);
    DB::connection('primary_race')->beginTransaction();
    DB::connection('primary_race')->statement("SET LOCAL lock_timeout='50ms'");
    $fixture = $this->crmContext;
    $race = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'primary_race', actor: $ctx->actor, principalId: $ctx->principalId);
    $observed = false;
    DB::listen(function ($query) use (&$observed, $fixture, $ctx, $race, $contact, $other): void {
        if ($observed || $query->connectionName !== 'pgsql' || ! str_contains($query->sql, '"crm_contacts"') || ! str_contains($query->sql, 'for update')) {
            return;
        }
        $observed = true;
        $fixture->context = $race;

        try {
            resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $other->id, isPrimary: true);
            test()->fail('Competing primary action did not block.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('55P03');
        } finally {
            $fixture->context = $ctx;
        }
    });

    try {
        resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
        $fixture->context = $race;
        expect(fn () => resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $other->id, isPrimary: true))->toThrow(ValidationException::class);
        expect($observed)->toBeTrue()->and(DB::table('crm_contact_company')->where('is_primary', true)->count())->toBe(1);
    } finally {
        $fixture->context = $ctx;
        DB::connection('primary_race')->rollBack();
        DB::purge('primary_race');
    }
});
