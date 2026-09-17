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
$foundationPackages = ['core', 'modules', 'tenancy', 'auth', 'tasks', 'api', 'recurrence'];

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

test('foundation packages declare no upward composer dependencies', function () use ($foundationPackages): void {
    $offenders = [];

    foreach ($foundationPackages as $pkg) {
        $composer = json_decode((string) file_get_contents(SourceScanner::packagePath($pkg, 'composer.json')), associative: true, flags: JSON_THROW_ON_ERROR);

        foreach (array_keys($composer['require'] ?? []) as $dependency) {
            if (str_starts_with($dependency, 'flex/')) {
                $offenders[] = $pkg.':'.$dependency;
            }
        }
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
        '/\buse\s+(CoreX\\\\(Modules|Auth|Tasks|Api)|Flex)\\\\/',
    );

    expect($offenders)->toBe([]);
});

test('tenant content does not bypass its owning storage adapter', function (): void {
    $offenders = SourceScanner::linesMatching(
        SourceScanner::packagePath('tenancy', 'src'),
        '/\\b(?:Storage::disk|FilesystemManager)\\b/',
        ['php'],
    );

    $allowed = [
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/Storage/ObjectTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
    ];

    $unexpected = array_values(array_filter(
        $offenders,
        static fn (string $hit): bool => ! in_array(explode(':', $hit, 2)[0], $allowed, strict: true),
    ));

    expect($unexpected)->toBe([]);
});

// (3) No product bindings in the TEXT of foundation sources (P2.17, OQ-17/D94).
// Rules 1/2 model namespaces and the require-graph; a `flex` literal in DDL, a
// composer description or a README creates no code dependency and passed those
// rules silently — the 2026-07-26 inventory found 11 such files while the gate
// stayed green. Layer 0 (D91) is literal: "no Flex binding at all, neither in
// identifiers nor in DDL nor in descriptions" — so the text is scanned too.
test('foundation packages carry no flex product literals', function () use ($foundationPackages): void {
    // EXPLICIT allow-list — every entry carries its reason and decision. Widening
    // it "to make the gate pass" is forbidden (P2.17 Code Guidance): a literal
    // that fits no reason below gets CLEANED, not permitted. Directory-wide
    // exemptions are banned outright — they would re-open the hole.
    $allowedLine = [
        // `flex/crm` / `Flex\Crm` — the TARGET name of the L2 thematic package
        // (D82/D70). Naming the neighbouring layer creates no dependency on it;
        // packages/tasks/README.md uses it precisely to declare isolation FROM it.
        '#flex/crm#i',
        '#Flex\\\\Crm#',
    ];

    // Whole-file allowance, by filename, for history that must not be rewritten.
    $allowedFile = [
        // CHANGELOG entries record the `flex/modules` → `corex/modules` and
        // `flex:modules:*` → `corex:modules:*` renames themselves (P2.0/P3.3).
        // Scrubbing the old names would make the changelog false.
        'CHANGELOG.md',
    ];

    $offenders = [];

    foreach ($foundationPackages as $pkg) {
        // php/json/md/sql — the source classes the doctrine speaks about
        // (code, package metadata, public texts, DDL fixtures). Generated
        // artifacts (`composer.lock`) are out: they mirror upstream text
        // (`symfony/flex`, `doctrine/lexer`), not this repo's authorship.
        $hits = SourceScanner::linesMatching(
            SourceScanner::packagePath($pkg),
            '/flex/i',
            ['php', 'json', 'md', 'sql'],
        );

        foreach ($hits as $hit) {
            [$path] = explode(':', $hit, 2);

            if (\in_array(basename($path), $allowedFile, strict: true)) {
                continue;
            }

            foreach ($allowedLine as $allowed) {
                if (preg_match($allowed, $hit) === 1) {
                    continue 2;
                }
            }

            $offenders[] = $hit;
        }
    }

    expect($offenders)->toBe([]);
});
