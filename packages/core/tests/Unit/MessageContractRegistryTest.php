<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Internal\ArrayMessageContractRegistry;

function registrySampleContract(
    string $name = 'search.document.indexed',
    int $version = 1,
): MessageContract {
    return new MessageContract(
        name: $name,
        version: $version,
        class: MessageClass::IntegrationEvent,
        owner: 'acme/search',
        maxPiiClass: PiiClass::Pseudonymous,
        channel: 'search',
    );
}

it('finds declared contracts by name and version', function (): void {
    $contract = registrySampleContract();
    $registry = new ArrayMessageContractRegistry(contracts: [$contract]);

    expect($registry->has('search.document.indexed', 1))->toBeTrue()
        ->and($registry->get('search.document.indexed', 1))->toBe($contract);
});

it('refuses undeclared contracts with MSG-002', function (): void {
    $registry = new ArrayMessageContractRegistry(contracts: []);

    expect(fn (): MessageContract => $registry->get('missing.event', 1))
        ->toThrow(UnknownMessageContract::class, 'MSG-002');
});

it('rejects duplicate identities when building the registry', function (): void {
    $first = registrySampleContract();
    $second = registrySampleContract();

    new ArrayMessageContractRegistry(contracts: [$first, $second]);
})->throws(UnknownMessageContract::class, 'declared more than once');

it('keeps v1 and v2 of the same name separate', function (): void {
    $v1 = registrySampleContract(version: 1);
    $v2 = registrySampleContract(version: 2);
    $registry = new ArrayMessageContractRegistry(contracts: [$v2, $v1]);

    expect($registry->get('search.document.indexed', 1)->version)->toBe(1)
        ->and($registry->get('search.document.indexed', 2)->version)->toBe(2);
});

it('lists all contracts sorted by id', function (): void {
    $registry = new ArrayMessageContractRegistry(contracts: [
        registrySampleContract(name: 'z.last', version: 1),
        registrySampleContract(name: 'a.first', version: 1),
        registrySampleContract(name: 'a.first', version: 2),
    ]);

    expect(array_map(static fn (MessageContract $c): string => $c->id(), $registry->all()))
        ->toBe(['a.first.v1', 'a.first.v2', 'z.last.v1']);
});

it('binds an empty registry by default in the container', function (): void {
    $registry = $this->app->make(MessageContractRegistry::class);

    expect($registry)->toBeInstanceOf(ArrayMessageContractRegistry::class)
        ->and($registry->all())->toBe([]);
});
