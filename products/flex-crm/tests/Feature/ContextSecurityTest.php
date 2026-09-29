<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\ModuleRegistry;
use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Company\Events\CompanyCreated;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;
use Flex\Crm\Contact\Events\ContactCompanyAssociated;
use Flex\Crm\Contact\Events\ContactCreated;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Contact\Models\ContactCompany;
use Flex\Crm\Context\Data\Actor;
use Flex\Crm\Context\Data\ExecutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('fails closed for disabled CRM and tenant context mismatch', function (): void {
    $ctx = $this->crmContext->current();
    $modules = Mockery::mock(ModuleRegistry::class);
    $modules->shouldReceive('isActive')->andReturn(false);
    app()->instance(ModuleRegistry::class, $modules);
    expect(fn () => app(CreateCompany::class)->handle(new CompanyData(name: 'Disabled')))->toThrow(AuthorizationException::class);
    $tenants = Mockery::mock(TenantContextResolver::class);
    $tenants->shouldReceive('current')->andReturn(new TenantContext(account: $ctx->tenant->account, boxed: true));
    app()->instance(TenantContextResolver::class, $tenants);
    expect(fn () => Company::count())->toThrow(AuthorizationException::class);
});

it('requires account-level capability when no workspace exists', function (): void {
    $ctx = $this->crmContext->current();
    $this->crmContext->context = new ExecutionContext(tenant: new TenantContext($ctx->tenant->account), connectionName: 'testbench', actor: $ctx->actor, principalId: $ctx->principalId);
    expect(fn () => Company::count())->toThrow(AuthorizationException::class);
});

it('rejects malformed actor identities and foreign profile IDs', function (): void {
    expect(fn () => new Actor(source: 'support', id: 'untrusted', name: 'Unknown'))->toThrow(ValidationException::class);
    expect(fn () => new Actor(source: 'user', id: '', name: 'Empty'))->toThrow(ValidationException::class);
    expect(fn () => app(CreateCompany::class)->handle(new CompanyData(name: 'Invalid owner', ownerId: 'u1')))->toThrow(AuthorizationException::class);
});

it('overwrites forged creation provenance and prevents changing it afterward', function (): void {
    $ctx = $this->crmContext->current();
    $company = new Company(['name' => 'Guarded', 'workspace_id' => $ctx->tenant->workspace->id]);
    $company->forceFill(['created_by' => ['source' => 'system', 'id' => 'forged', 'name' => 'Forgery']])->save();
    expect($company->created_by)->toBe($ctx->actor->toArray());
    $company->forceFill(['created_by' => ['source' => 'system', 'id' => 'forged', 'name' => 'Forgery']]);
    expect(fn () => $company->save())->toThrow(AuthorizationException::class);
});

it('rejects direct foreign workspace writes and foreign connection queries', function (): void {
    expect(fn () => Company::create(['name' => 'Foreign', 'workspace_id' => (string) Str::uuid7()]))->toThrow(AuthorizationException::class);
    config(['database.connections.other' => config('database.connections.testbench')]);
    expect(fn () => Company::on('other')->count())->toThrow(AuthorizationException::class);
    expect(fn () => Company::on('other')->create(['name' => 'Foreign', 'workspace_id' => $this->crmContext->current()->tenant->workspace->id]))->toThrow(AuthorizationException::class);
    expect(fn () => ContactCompany::on('other')->count())->toThrow(AuthorizationException::class);
});

it('guards direct association writes against cross-scope and foreign connection input', function (): void {
    $ctx = $this->crmContext->current();
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'testbench', actor: $ctx->actor, principalId: $ctx->principalId, allowAccountLevel: true);
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Global', accountLevel: true));
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Local'));
    expect(fn () => ContactCompany::create(['contact_id' => $contact->id, 'company_id' => $company->id]))->toThrow(AuthorizationException::class);
    config(['database.connections.other' => config('database.connections.testbench')]);
    expect(fn () => ContactCompany::on('other')->create(['contact_id' => $contact->id, 'company_id' => $company->id]))->toThrow(AuthorizationException::class);
});

it('rechecks trusted provenance after late creating observers', function (): void {
    Company::creating(static function (Company $record): void {
        $record->forceFill(['created_by' => ['source' => 'system', 'id' => 'forged', 'name' => 'Forged']]);
    });
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Late observer'));
    expect($company->created_by)->toBe($this->crmContext->current()->actor->toArray());
});

it('rejects a late observer changing the workspace and guards quiet writes', function (): void {
    $ctx = $this->crmContext->current();
    $quiet = new Company(['name' => 'Quiet foreign', 'workspace_id' => (string) Str::uuid7()]);
    expect(fn () => $quiet->saveQuietly())->toThrow(AuthorizationException::class);
    $quiet = app(CreateCompany::class)->handle(new CompanyData(name: 'Quiet provenance'));
    $quiet->forceFill(['created_by' => ['source' => 'system', 'id' => 'forged', 'name' => 'Forged']]);
    expect(fn () => $quiet->saveQuietly())->toThrow(AuthorizationException::class);
    Company::creating(static function (Company $record): void {
        $record->workspace_id = (string) Str::uuid7();
    });
    expect(fn () => app(CreateCompany::class)->handle(new CompanyData(name: 'Late workspace')))->toThrow(AuthorizationException::class);
    expect(DB::table('crm_companies')->count())->toBe(1);
});

it('rejects a late observer changing association endpoints', function (): void {
    $ctx = $this->crmContext->current();
    $this->crmContext->context = new ExecutionContext(tenant: $ctx->tenant, connectionName: 'testbench', actor: $ctx->actor, principalId: $ctx->principalId, allowAccountLevel: true);
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Local'));
    $global = app(CreateCompany::class)->handle(new CompanyData(name: 'Global', accountLevel: true));
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Local'));
    ContactCompany::creating(static function (ContactCompany $link) use ($global): void {
        $link->company_id = $global->id;
    });
    expect(fn () => app(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id))->toThrow(AuthorizationException::class);
    expect(DB::table('crm_contact_company')->count())->toBe(0);
});

it('treats observer veto as failed mutation without dispatching success events', function (): void {
    Event::fake([CompanyCreated::class, ContactCreated::class, ContactCompanyAssociated::class]);
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Existing'));
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Existing'));
    Event::fake([CompanyCreated::class, ContactCreated::class, ContactCompanyAssociated::class]);
    Company::creating(static fn (): bool => false);
    Contact::creating(static fn (): bool => false);
    ContactCompany::creating(static fn (): bool => false);
    expect(fn () => app(CreateCompany::class)->handle(new CompanyData(name: 'Veto')))->toThrow(ValidationException::class);
    expect(fn () => app(CreateContact::class)->handle(new ContactData(nameFirst: 'Veto')))->toThrow(ValidationException::class);
    expect(fn () => app(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id))->toThrow(ValidationException::class);
    Event::assertNothingDispatched();
    expect(DB::table('crm_companies')->count())->toBe(1)->and(DB::table('crm_contacts')->count())->toBe(1)
        ->and(DB::table('crm_contact_company')->count())->toBe(0);
});
