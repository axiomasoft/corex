<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\SingleAccountResolver;

it('resolves a boxed TenantContext via SingleAccountResolver', function (): void {
    $context = app(TenantContextResolver::class)->current();

    expect(app(TenantContextResolver::class))->toBeInstanceOf(SingleAccountResolver::class)
        ->and($context->isBoxed())->toBeTrue()
        ->and($context->workspace)->toBeNull()
        ->and($context->account)->toBeInstanceOf(AccountRef::class);
});

it('forWorkspace returns a new context carrying the workspace, boxed flag preserved', function (): void {
    $context = (new SingleAccountResolver)->current();
    $workspace = new WorkspaceRef(
        id: 'ws-1',
        slug: 'acme-hq',
        type: 'company',
        path: 'acme-hq',
        parentId: null,
    );

    $withWorkspace = $context->forWorkspace($workspace);

    expect($withWorkspace)->not->toBe($context)
        ->and($withWorkspace->workspace)->toBe($workspace)
        ->and($withWorkspace->account)->toBe($context->account)
        ->and($withWorkspace->isBoxed())->toBeTrue();
});

it('SingleAccountResolver::current() is deterministic', function (): void {
    $resolver = new SingleAccountResolver;

    $first = $resolver->current();
    $second = $resolver->current();

    expect($first->account->id)->toBe($second->account->id)
        ->and($first->account->slug)->toBe($second->account->slug)
        ->and($first->account->status)->toBe($second->account->status);
});

it('AccountRef and WorkspaceRef are immutable value objects matching the canonical shape', function (): void {
    $account = new AccountRef(
        id: 'acc-1',
        slug: 'acme',
        status: 'active',
        features: ['billing'],
        limits: ['seats' => 10],
    );
    $workspace = new WorkspaceRef(
        id: 'ws-1',
        slug: 'acme-hq',
        type: 'company',
        path: 'acme-hq',
        parentId: null,
    );

    expect($account->id)->toBe('acc-1')
        ->and($account->slug)->toBe('acme')
        ->and($account->status)->toBe('active')
        ->and($account->features)->toBe(['billing'])
        ->and($account->limits)->toBe(['seats' => 10])
        ->and($workspace->type)->toBe('company')
        ->and($workspace->parentId)->toBeNull();
});
