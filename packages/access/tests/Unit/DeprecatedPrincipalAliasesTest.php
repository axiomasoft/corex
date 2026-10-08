<?php

declare(strict_types=1);

use CoreX\Access\Access\Laravel\PrincipalIdentity;
use CoreX\Access\Contracts\Principal as DeprecatedPrincipal;
use CoreX\Access\Enums\PrincipalType as DeprecatedPrincipalType;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;

// Audit 2026-10-08 P-02: one principal concept, owned by the kernel. The
// access names stay as class_alias names of the SAME types until 2.0.
it('keeps the deprecated access principal names as aliases of the kernel types', function (): void {
    expect(new ReflectionClass(DeprecatedPrincipal::class)->getName())->toBe(Principal::class)
        ->and(new ReflectionEnum(DeprecatedPrincipalType::class)->getName())->toBe(PrincipalType::class)
        ->and(DeprecatedPrincipalType::Agent)->toBe(PrincipalType::Agent);
});

it('lets the access identity serve as the operation caller without an adapter', function (): void {
    $identity = new PrincipalIdentity(type: DeprecatedPrincipalType::ApiKey, id: 'key-1');

    expect($identity)->toBeInstanceOf(Principal::class)
        ->and($identity)->toBeInstanceOf(DeprecatedPrincipal::class)
        ->and($identity->principalType())->toBe(PrincipalType::ApiKey)
        ->and($identity->principalId())->toBe('key-1');
});
