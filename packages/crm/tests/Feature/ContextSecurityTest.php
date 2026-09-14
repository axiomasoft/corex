<?php

declare(strict_types=1);

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Data\Actor;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;
use Flex\Crm\Data\ExecutionContext;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\ContactCompany;
use Illuminate\Auth\Access\AuthorizationException;
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
