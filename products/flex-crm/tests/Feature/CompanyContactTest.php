<?php

declare(strict_types=1);

use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;

it('creates a company and a contact linked to it', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Acme Inc', ownerId: $this->crmContext->context->principalId, inn: '7700000000'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: 'Jane', emails: [['value' => 'jane@acme.test']]));
    resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id);

    expect($contact->companies()->sole()->is($company))->toBeTrue()
        ->and($company->contacts()->count())->toBe(1);
});
