<?php

declare(strict_types=1);

use Flex\Crm\Actions\AssociateContactCompany;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateContact;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ContactData;
use Illuminate\Validation\ValidationException;

it('creates approved records and a typed primary association', function (): void {
    $company = app(CreateCompany::class)->handle(new CompanyData(name: 'Acme', inn: '7700000000', domain: 'acme.test'));
    $contact = app(CreateContact::class)->handle(new ContactData(nameFirst: ' Jane ', nameLast: ' Doe ', emails: [['value' => 'JANE@acme.test']]));
    $link = app(AssociateContactCompany::class)->handle(contactId: $contact->id, companyId: $company->id, isPrimary: true);
    expect($contact->fresh()->display_name)->toBe('Doe Jane')
        ->and($contact->emails[0]['norm'])->toBe('jane@acme.test')
        ->and($contact->created_by['source'])->toBe('user')
        ->and($link->is_primary)->toBeTrue()
        ->and($company->contacts()->sole()->id)->toBe($contact->id);
});

it('rejects nameless contacts without usable channels', function (): void {
    app(CreateContact::class)->handle(new ContactData);
})->throws(ValidationException::class);

it('normalizes Unicode whitespace before testing empty name components', function (): void {
    $contact = app(CreateContact::class)->handle(new ContactData(
        nameFirst: "\u{00a0} Jane\u{2003}", nameLast: "\u{00a0}\u{2003}",
    ));
    expect($contact->name_first)->toBe('Jane')->and($contact->name_last)->toBeNull()
        ->and($contact->display_name)->toBe('Jane');
});

it('rejects invalid UTF-8 names rather than silently dropping their content', function (): void {
    app(CreateContact::class)->handle(new ContactData(nameFirst: "\xff", emails: [['value' => 'valid@example.test']]));
})->throws(ValidationException::class);
