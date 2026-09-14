<?php

declare(strict_types=1);

use Flex\Crm\Actions\AssociateContactCompany;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;

it('creates a company and a contact linked to it', function (): void {
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Acme Inc', ownerId: $this->crmContext->context->principalId, inn: '7700000000'));
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane', emails: [['value' => 'jane@acme.test']]));
    app(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);

    expect($contact->companies()->sole()->is($company))->toBeTrue()
        ->and($company->contacts()->count())->toBe(1);
});
