<?php

declare(strict_types=1);

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Context\Enums\PrincipalType;

it('returns an empty password string for an identity without a local hash', function (PrincipalType $type): void {
    $identity = new PrincipalIdentity(type: $type, id: '019b30e6-5861-7000-8000-000000000001');

    expect($identity->getAuthPassword())->toBe('');
})->with(PrincipalType::cases());

it('returns the supplied password hash unchanged', function (): void {
    $identity = new PrincipalIdentity(type: PrincipalType::User, id: '019b30e6-5861-7000-8000-000000000001', passwordHash: 'stored-hash');

    expect($identity->getAuthPassword())->toBe('stored-hash');
});
