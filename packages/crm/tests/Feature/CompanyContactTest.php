<?php

declare(strict_types=1);

use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;

it('creates a company and a contact linked to it', function (): void {
    $company = app(CreateCompany::class)->handle('Acme Inc', ownerId: 'u1', inn: '7700000000');
    $contact = app(CreateContact::class)->handle(firstName: 'Jane', email: 'jane@acme.test', companyId: $company->id);

    expect($contact->company->is($company))->toBeTrue()
        ->and($company->contacts()->count())->toBe(1);
});
