<?php

declare(strict_types=1);

use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Actions\DeleteCompany;
use Flex\Crm\Company\Actions\UpdateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;
use Illuminate\Validation\ValidationException;

it('creates a company and a contact linked to it', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Acme Inc', ownerId: $this->crmContext->context->principalId, inn: '7700000000'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane', emails: [['value' => 'jane@acme.test']]));
    resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);

    expect($contact->companies()->sole()->is($company))->toBeTrue()
        ->and($company->contacts()->count())->toBe(1);
});

it('updates admitted fields, rejects protected input, and preserves association history through soft delete', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Original', legalName: 'Original LLC', domain: 'original.test'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane'));
    $link = resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);
    $update = resolve(UpdateCompany::class);
    $delete = resolve(DeleteCompany::class);
    $owner = $company->getConnection();

    expect(fn () => $update->handle(companyId: $company->id, changes: ['workspace_id' => null]))
        ->toThrow(ValidationException::class);

    $modified = $update->handle(companyId: $company->id, changes: ['name' => '  New  name ', 'legal_name' => null]);

    expect($modified->id)->toBe($company->id)
        ->and($modified->name)->toBe('New name')
        ->and($modified->legal_name)->toBeNull()
        ->and($modified->domain)->toBe('original.test')
        ->and($modified->workspace_id)->toBe($company->workspace_id)
        ->and($modified->created_by)->toBe($company->created_by);

    $delete->handle(companyId: $company->id);

    expect(Company::on($company->getConnectionName())->find($company->id))->toBeNull()
        ->and($contact->companies()->count())->toBe(0)
        ->and($owner->table('crm_companies')->where('id', $company->id)->value('deleted_at'))->not->toBeNull()
        ->and($owner->table('crm_contact_company')->where('id', $link->id)->value('company_id'))->toBe($company->id);
});

it('rolls back a vetoed company update and deletion on the owner connection', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Original'));
    Company::updating(static fn (): bool => false);
    Company::deleting(static fn (): bool => false);

    expect(fn () => resolve(UpdateCompany::class)->handle(companyId: $company->id, changes: ['name' => 'Refused']))
        ->toThrow(ValidationException::class);
    expect(fn () => resolve(DeleteCompany::class)->handle(companyId: $company->id))
        ->toThrow(ValidationException::class);

    expect($company->fresh()->name)->toBe('Original')
        ->and($company->fresh()->deleted_at)->toBeNull();
});
