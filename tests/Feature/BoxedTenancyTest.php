<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\NullTenancyManager;

/**
 * P2.5 — boxed TenancyManager binding (D10, B-11 §6.1 L892–907): no
 * root-DB/stancl requirement, SingleAccountResolver's constant context flows
 * through unchanged, no Stancl class is ever instantiated (axiomasoft/corex-tenancy is
 * not a dependency of axiomasoft/corex or this test suite — a `use Stancl\*` here
 * would be a hard composer error, not just a runtime skip; see also
 * `grep -r 'use Stancl' packages/core` in the item's Validation).
 */
it('boxed TenancyManager is the null-manager: constant context, initialized() true, initialize()/setWorkspace()/end() are no-ops (AC-28 г)', function (): void {
    $manager = app(TenancyManager::class);

    expect($manager)->toBeInstanceOf(NullTenancyManager::class)
        ->and($manager->initialized())->toBeTrue();

    $context = $manager->context();
    expect($context)->not->toBeNull()
        ->and($context->isBoxed())->toBeTrue();

    $manager->initialize(new AccountRef(id: 'other', slug: 'other', status: 'active', features: [], limits: []));
    $manager->setWorkspace(null);

    // no-ops — the boxed context is unchanged.
    expect($manager->context()->account->id)->toBe($context->account->id)
        ->and($manager->initialized())->toBeTrue();

    $manager->end();
    expect($manager->initialized())->toBeTrue();
});

it('runFor() on the boxed manager just runs the closure (no context switch)', function (): void {
    $manager = app(TenancyManager::class);

    $result = $manager->runFor(
        new AccountRef(id: 'x', slug: 'x', status: 'active', features: [], limits: []),
        null,
        fn (): string => 'ran',
    );

    expect($result)->toBe('ran');
});
