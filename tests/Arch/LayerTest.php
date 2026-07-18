<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

// P3.5 — deterministic machine gates for the layer doctrine (D70).
//
// Tool choice (Implementation Rules: pick by expressibility, negative-prove it):
// pest-plugin-arch's `expect('CoreX')->not->toUse('Flex')` was evaluated and
// REJECTED. In this monorepo the packages are composer `type:path` repos symlinked
// under vendor/, which pest-arch treats as vendor and excludes from broad-namespace
// enumeration — the rule passed VACUOUSLY against a planted Flex dependency
// (verified 2026-07-18: a CoreX\Modules probe importing Flex\Crm\* did not trip it,
// yet the naming an explicit FQCN did). A directory-scoped source scan enforces the
// same boundary deterministically and demonstrably reddens on a violation. deptrac
// (directory collectors) would also work but adds a dependency the require-graph +
// source scan already make unnecessary.

// Foundation = the CoreX\*-namespaced packages (L0 agnostic + L1 domain-base). The
// crm package IS the Flex\* thematic layer (L2, may depend on corex/*) — it is
// intentionally excluded from the "may not import Flex" scan.
$foundationPackages = ['core', 'modules', 'tenancy', 'auth', 'tasks'];

// (1) Foundation ↛ Thematic: no corex/* source may import a Flex\* namespace.
// The vendor inversion D70 fixed was exactly this kind of leak.
test('foundation packages do not import flex/* namespaces', function () use ($foundationPackages): void {
    $offenders = [];

    foreach ($foundationPackages as $pkg) {
        $offenders = [
            ...$offenders,
            ...SourceScanner::filesMatching(SourceScanner::packagePath($pkg, 'src'), '/\buse\s+Flex\\\\/'),
        ];
    }

    expect($offenders)->toBe([]);
});

// (2a) corex/core is the L0 agnostic platform: its composer require-graph must
// contain no sibling monorepo package (the item's own criterion — "core без
// require corex/*"). This is a package fact, not a namespace fact.
test('corex/core declares no monorepo package dependencies', function (): void {
    $composer = json_decode(
        (string) file_get_contents(SourceScanner::packagePath('core', 'composer.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $monorepoRequires = array_values(array_filter(
        array_keys($composer['require'] ?? []),
        static fn (string $pkg): bool => str_starts_with($pkg, 'corex/') || str_starts_with($pkg, 'flex/'),
    ));

    expect($monorepoRequires)->toBe([]);
});

// (2b) Source-level counterpart of 2a: core owns the CoreX\Tenancy\* contracts
// physically (boxed-safe, brain-recon §5), so those imports are legal; forbidden
// are the *sibling packages* Modules/Auth/Tasks and any Flex\* namespace.
test('corex/core source imports no sibling package namespace', function (): void {
    $offenders = SourceScanner::filesMatching(
        SourceScanner::packagePath('core', 'src'),
        '/\buse\s+(CoreX\\\\(Modules|Auth|Tasks)|Flex)\\\\/',
    );

    expect($offenders)->toBe([]);
});
