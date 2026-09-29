<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Internal\Schema\DefinitionEvolution;
use CoreX\CustomFields\Internal\Values\AttributeValueTransformerRegistry;

function evolutionDefinition(
    int $revision = 1,
    string $key = 'website',
    string $type = 'text',
    AttributeLifecycle $lifecycle = AttributeLifecycle::Active,
    array $readKeys = ['website'],
    array $writeKeys = ['website'],
    array $meta = [],
): AttributeDefinition {
    return new AttributeDefinition(
        id: AttributeId::v7('018f0b5d-0000-7000-8000-000000000010'),
        entity: 'contact',
        key: $key,
        revision: $revision,
        type: $type,
        lifecycle: $lifecycle,
        required: false,
        searchable: true,
        constraints: [],
        readKeys: $readKeys,
        writeKeys: $writeKeys,
        index: IndexPolicy::None,
        meta: $meta,
    );
}

function evolutionRegistry(): AttributeValueTransformerRegistry
{
    return new AttributeValueTransformerRegistry([
        'numeric-text' => [
            'forward' => static fn (mixed $value): int => (int) $value,
            'reverse' => static fn (mixed $value): string => (string) $value,
        ],
    ]);
}

it('enforces the closed attribute lifecycle graph', function (AttributeLifecycle $from, AttributeLifecycle $to, bool $allowed): void {
    $evolution = new DefinitionEvolution(evolutionRegistry());

    $attempt = fn (): mixed => $evolution->assertTransition($from, $to);

    if ($allowed) {
        expect($attempt())->toBeNull();

        return;
    }

    expect($attempt)->toThrow(InvalidArgumentException::class);
})->with([
    'draft to active' => [AttributeLifecycle::Draft, AttributeLifecycle::Active, true],
    'active to migrating' => [AttributeLifecycle::Active, AttributeLifecycle::Migrating, true],
    'deprecated to active' => [AttributeLifecycle::Deprecated, AttributeLifecycle::Active, true],
    'migrating to deprecated' => [AttributeLifecycle::Migrating, AttributeLifecycle::Deprecated, true],
    'active cannot retire directly' => [AttributeLifecycle::Active, AttributeLifecycle::Retired, false],
    'migrating cannot retire' => [AttributeLifecycle::Migrating, AttributeLifecycle::Retired, false],
    'retired is terminal' => [AttributeLifecycle::Retired, AttributeLifecycle::Active, false],
]);

it('builds expand switch and contract revisions with the required paths', function (): void {
    $evolution = new DefinitionEvolution(evolutionRegistry());
    $expanded = $evolution->expand(evolutionDefinition(), 'score', 'int', 'numeric-text');

    expect($expanded->revision)->toBe(2)
        ->and($expanded->lifecycle)->toBe(AttributeLifecycle::Migrating)
        ->and($expanded->readKeys)->toBe(['score', 'website'])
        ->and($expanded->writeKeys)->toBe(['website', 'score'])
        ->and($evolution->reservations($expanded))->toBe(['score' => 'current', 'website' => 'alias']);

    $switchedAt = new DateTimeImmutable('2026-09-24T10:00:00+00:00');
    $digest = str_repeat('a', 64);
    $switched = $evolution->switch($expanded, $digest, $digest, $digest, $switchedAt);

    expect($switched->revision)->toBe(3)
        ->and($switched->lifecycle)->toBe(AttributeLifecycle::Active)
        ->and($switched->readKeys)->toBe(['score', 'website'])
        ->and($switched->writeKeys)->toBe(['score'])
        ->and($evolution->reservations($switched))->toBe(['score' => 'current', 'website' => 'alias']);

    $contracted = $evolution->contract(
        $switched,
        new DateTimeImmutable('2026-09-25T10:00:00+00:00'),
        new DateInterval('PT12H'),
        0,
    );

    expect($contracted->revision)->toBe(4)
        ->and($contracted->readKeys)->toBe(['score'])
        ->and($contracted->writeKeys)->toBe(['score'])
        ->and($evolution->reservations($contracted))->toBe(['score' => 'current']);
});

it('blocks type changes without a transformer and switch or contract without proof', function (): void {
    $evolution = new DefinitionEvolution(evolutionRegistry());

    expect(fn (): mixed => $evolution->expand(evolutionDefinition(), 'score', 'int'))
        ->toThrow(InvalidArgumentException::class, 'registered reversible transformer');

    $forwardOnly = new DefinitionEvolution(new AttributeValueTransformerRegistry([
        'forward-only' => ['forward' => static fn (mixed $value): int => (int) $value],
    ]));
    expect(fn (): mixed => $forwardOnly->expand(evolutionDefinition(), 'score', 'int', 'forward-only'))
        ->toThrow(InvalidArgumentException::class, 'reversible transformer');

    $expanded = $evolution->expand(evolutionDefinition(), 'score', 'int', 'numeric-text');
    expect(fn (): mixed => $evolution->switch(
        $expanded,
        str_repeat('a', 64),
        str_repeat('a', 64),
        str_repeat('b', 64),
        new DateTimeImmutable,
    ))->toThrow(InvalidArgumentException::class, 'digest');

    $digest = str_repeat('a', 64);
    $switched = $evolution->switch($expanded, $digest, $digest, $digest, new DateTimeImmutable('2026-09-24T10:00:00+00:00'));
    expect(fn (): mixed => $evolution->contract($switched, new DateTimeImmutable('2026-09-24T11:00:00+00:00'), new DateInterval('PT12H'), 0))
        ->toThrow(InvalidArgumentException::class, 'grace period')
        ->and(fn (): mixed => $evolution->contract($switched, new DateTimeImmutable('2026-09-25T10:00:00+00:00'), new DateInterval('PT12H'), 1))
        ->toThrow(InvalidArgumentException::class, 'fallback reads');
});

it('makes both rollback points executable', function (): void {
    $evolution = new DefinitionEvolution(evolutionRegistry());
    $expanded = $evolution->expand(evolutionDefinition(), 'score', 'int', 'numeric-text');
    $beforeSwitch = $evolution->rollbackBeforeSwitch($expanded);

    expect($beforeSwitch->key)->toBe('website')
        ->and($beforeSwitch->type)->toBe('text')
        ->and($beforeSwitch->lifecycle)->toBe(AttributeLifecycle::Active)
        ->and($evolution->reservations($beforeSwitch))->toBe(['website' => 'current']);

    $digest = str_repeat('a', 64);
    $switched = $evolution->switch($expanded, $digest, $digest, $digest, new DateTimeImmutable);
    $afterSwitch = $evolution->rollbackAfterSwitch($switched);

    expect($afterSwitch->key)->toBe('website')
        ->and($afterSwitch->type)->toBe('text')
        ->and($afterSwitch->lifecycle)->toBe(AttributeLifecycle::Migrating)
        ->and($afterSwitch->readKeys)->toBe(['website', 'score'])
        ->and($afterSwitch->writeKeys)->toBe(['score', 'website'])
        ->and($evolution->reservations($afterSwitch))->toBe(['website' => 'current', 'score' => 'alias']);
});

it('restores a deprecated source lifecycle when aborting before switch', function (): void {
    $evolution = new DefinitionEvolution(evolutionRegistry());
    $deprecated = evolutionDefinition(lifecycle: AttributeLifecycle::Deprecated);
    $expanded = $evolution->expand($deprecated, 'score', 'int', 'numeric-text');

    expect($evolution->rollbackBeforeSwitch($expanded)->lifecycle)->toBe(AttributeLifecycle::Deprecated);
});

it('checks transformer determinism and supports reversible values', function (): void {
    $registry = evolutionRegistry();

    expect($registry->forward('numeric-text', '42'))->toBe(42)
        ->and($registry->reverse('numeric-text', 42))->toBe('42');

    $counter = 0;
    $impure = new AttributeValueTransformerRegistry([
        'impure' => ['forward' => static function () use (&$counter): int {
            return ++$counter;
        }],
    ]);

    expect(fn (): mixed => $impure->forward('impure', null))
        ->toThrow(RuntimeException::class, 'not deterministic');
});
