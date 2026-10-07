<?php

declare(strict_types=1);

use Flex\Crm\Company\Actions\CreateCompany;
use Flex\Crm\Company\Data\CompanyData;
use Flex\Crm\Contact\Actions\AssociateContactCompany;
use Flex\Crm\Contact\Actions\CreateContact;
use Flex\Crm\Contact\Data\ContactData;
use Illuminate\Validation\ValidationException;

it('creates approved records and a typed primary association', function (): void {
    $company = resolve(CreateCompany::class)->handle(new CompanyData(name: 'Acme', inn: '7700000000', domain: 'acme.test'));
    $contact = resolve(CreateContact::class)->handle(new ContactData(nameFirst: ' Jane ', nameLast: ' Doe ', emails: [['value' => 'JANE@acme.test']]));
    $link = resolve(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
    expect($contact->fresh()->display_name)->toBe('Doe Jane')
        ->and($contact->emails[0]['norm'])->toBe('jane@acme.test')
        ->and($contact->created_by['source'])->toBe('user')
        ->and($link->is_primary)->toBeTrue()
        ->and($company->contacts()->sole()->id)->toBe($contact->id);
});

it('rejects nameless contacts without usable channels', function (): void {
    resolve(CreateContact::class)->handle(new ContactData);
})->throws(ValidationException::class);

it('normalizes Unicode whitespace before testing empty name components', function (): void {
    $contact = resolve(CreateContact::class)->handle(new ContactData(
        nameFirst: "\u{00a0} Jane\u{2003}", nameLast: "\u{00a0}\u{2003}",
    ));
    expect($contact->name_first)->toBe('Jane')->and($contact->name_last)->toBeNull()
        ->and($contact->display_name)->toBe('Jane');
});

it('rejects invalid UTF-8 names rather than silently dropping their content', function (): void {
    resolve(CreateContact::class)->handle(new ContactData(nameFirst: "\xff", emails: [['value' => 'valid@example.test']]));
})->throws(ValidationException::class);
