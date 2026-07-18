<?php

declare(strict_types=1);

use CoreX\Contracts\TenantService;
use CoreX\Tests\Stubs\Tenant;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Models\Company;

beforeEach(function (): void {
    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
});

it('creates a company and a contact linked to it', function (): void {
    $company = app(CreateCompany::class)->handle('Acme Inc', ownerId: 'u1', inn: '7700000000');
    $contact = app(CreateContact::class)->handle(firstName: 'Jane', email: 'jane@acme.test', companyId: $company->id);

    expect($company->tenant_id)->toBe('tenant-a')
        ->and($contact->tenant_id)->toBe('tenant-a')
        ->and($contact->company->is($company))->toBeTrue()
        ->and($company->contacts()->count())->toBe(1);
});

it('isolates companies by tenant', function (): void {
    app(CreateCompany::class)->handle('A');

    app(TenantService::class)->setCurrent(new Tenant('tenant-b'));
    expect(Company::query()->count())->toBe(0);

    app(TenantService::class)->setCurrent(new Tenant('tenant-a'));
    expect(Company::query()->count())->toBe(1);
});
