<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

// P6.2 — ten-zone arch gate (D32/D101). Matrix is copied from the design, not read at runtime.
// PubSub/ is Messaging by path. ALLOWED['Foundation'] stays empty.

/**
 * @var array<string, list<string>>
 */
const CORE_ZONE_ALLOWED = [
    'Foundation' => [],
    'TenancyContracts' => ['Foundation'],
    'Audit' => ['Foundation', 'TenancyContracts'],
    'Settings' => ['Foundation', 'TenancyContracts'],
    'SavedViews' => ['Foundation', 'TenancyContracts'],
    'Storage' => ['Foundation', 'TenancyContracts'],
    'Messaging' => ['Foundation', 'TenancyContracts'],
    'Features' => ['Foundation', 'TenancyContracts'],
    'Departments' => ['Foundation', 'TenancyContracts'],
    'Money' => ['Foundation', 'TenancyContracts'],
];

/** @var list<string> */
const CORE_ZONE_COMPOSITION = [
    'packages/core/src/CoreServiceProvider.php',
    'packages/core/src/Support/Config.php',
    'packages/core/src/Support/CoreOptions.php',
    'packages/core/src/Support/Console/ValidateConfigCommand.php',
    'packages/core/src/Support/ServiceProviderModuleRegistrar.php',
    'packages/core/src/Support/PackageRegistrar.php',
    'packages/core/src/Support/RegistersNothingOnBoot.php',
];

const CORE_ZONE_E_BASEMODEL = 'packages/core/src/Models/BaseModel.php';

/**
 * Closed public edges (D116). Not an ALLOWED widening: any other symbol stays R-Z1.
 *
 * @var array<string, list<string>>
 */
const CORE_ZONE_EXACT_IMPORTS = [
    'packages/core/src/Support/Data/DataScope.php' => [
        'CoreX\\Tenancy\\TenantContext',
    ],
    'packages/core/src/Events/DomainEvent.php' => [
        'CoreX\\Tenancy\\TenantContext',
        'CoreX\\Tenancy\\Contracts\\TenantContextResolver',
    ],
    'packages/core/src/Events/FeatureFlagToggled.php' => [
        'CoreX\\Contracts\\SettingsScope',
    ],
    'packages/core/src/Features/DatabaseFeatureFlags.php' => [
        'CoreX\\Contracts\\SettingsScope',
        'CoreX\\Enums\\SettingScope',
    ],
];

/**
 * Exact Foundation support types (D101). Any other unclassified Support file is R-Z0.
 *
 * @var list<string>
 */
const CORE_ZONE_FOUNDATION_SUPPORT = [
    'Support/ViolationList.php',
    'Support/InvalidConfiguration.php',
    'Support/SecretRef.php',
];

/**
 * Prefixes under packages/core/src/. Longer paths are listed before shorter ones.
 *
 * @var array<string, string>
 */
const CORE_ZONE_PREFIX = [
    'Support/Time/' => 'Foundation',
    'Support/Data/' => 'Foundation',
    'Support/Schema/' => 'Foundation',
    'PHPStan/' => 'Foundation',
    'Audit/' => 'Audit',
    'Departments/' => 'Departments',
    'Features/' => 'Features',
    'Money/' => 'Money',
    'PubSub/' => 'Messaging',
    'Messaging/' => 'Messaging',
    'Settings/' => 'Settings',
    'Storage/' => 'Storage',
    'Tenancy/' => 'TenancyContracts',
    'Views/' => 'SavedViews',
];

/**
 * Mixed catalogues from the normative map (design §3.2 plus files classified by P6.1).
 *
 * @var array<string, string>
 */
const CORE_ZONE_DUMP = [
    'Contracts/Auditable.php' => 'Audit',
    'Contracts/AuditEntry.php' => 'Audit',
    'Contracts/AuditLogger.php' => 'Audit',
    'Contracts/AuditRetention.php' => 'Audit',
    'Contracts/DataScopeAuthorizer.php' => 'Foundation',
    'Contracts/DataScopeCodec.php' => 'Foundation',
    'Contracts/DescribesError.php' => 'Foundation',
    'Contracts/DurablePayload.php' => 'Foundation',
    'Contracts/ModuleRegistrar.php' => 'Foundation',
    'Contracts/DepartmentRepository.php' => 'Departments',
    'Contracts/FeatureFlagDefinition.php' => 'Features',
    'Contracts/FeatureFlags.php' => 'Features',
    'Contracts/IdentityPasswordRehasher.php' => 'TenancyContracts',
    'Contracts/PubSub.php' => 'Messaging',
    'Contracts/WorkerRuntime.php' => 'Messaging',
    'Contracts/SavedViewRepository.php' => 'SavedViews',
    'Contracts/ViewAccessPolicy.php' => 'SavedViews',
    'Contracts/ViewSchemaProvider.php' => 'SavedViews',
    'Contracts/SettingDefaultsProvider.php' => 'Settings',
    'Contracts/SettingsRepository.php' => 'Settings',
    'Contracts/SettingsScope.php' => 'Settings',
    'Contracts/TenantStorage.php' => 'Storage',
    'Models/BaseModel.php' => 'Foundation',
    'Models/AuditLog.php' => 'Audit',
    'Models/Department.php' => 'Departments',
    'Models/FeatureFlag.php' => 'Features',
    'Models/FeatureOverride.php' => 'Features',
    'Models/Setting.php' => 'Settings',
    'Concerns/HasAuditLog.php' => 'Audit',
    'Concerns/HasConfiguredId.php' => 'Foundation',
    'Enums/ActorType.php' => 'Foundation',
    'Enums/ErrorCategory.php' => 'Foundation',
    'Enums/IdStrategy.php' => 'Foundation',
    'Enums/UnavailabilityReason.php' => 'Foundation',
    'Enums/ImpersonationRestrictedAction.php' => 'TenancyContracts',
    'Enums/MessageClass.php' => 'Messaging',
    'Enums/PiiClass.php' => 'Messaging',
    'Enums/PubSubDriver.php' => 'Messaging',
    'Enums/SettingScope.php' => 'Settings',
    'Events/Cancellable.php' => 'Foundation',
    'Events/DomainEvent.php' => 'Foundation',
    'Events/DepartmentMoved.php' => 'Departments',
    'Events/FeatureFlagToggled.php' => 'Features',
    'Events/SettingChanged.php' => 'Settings',
    'Exceptions/CentralContextException.php' => 'TenancyContracts',
    'Exceptions/ImpersonationRestrictedException.php' => 'TenancyContracts',
    'Exceptions/ScopeContextMismatch.php' => 'TenancyContracts',
    'Exceptions/CrossWorkspaceMoveException.php' => 'Departments',
    'Exceptions/DepartmentCycleException.php' => 'Departments',
    'Exceptions/InboxRefused.php' => 'Messaging',
    'Exceptions/InvalidMessage.php' => 'Messaging',
    'Exceptions/InvalidScopePayload.php' => 'Foundation',
    'Exceptions/OutboxLeaseLost.php' => 'Messaging',
    'Exceptions/OutboxRefused.php' => 'Messaging',
    'Exceptions/SettingValueTooLargeException.php' => 'Settings',
    'Exceptions/UnknownMessageContract.php' => 'Messaging',
    'Exceptions/UnsupportedScopeException.php' => 'Settings',
    'Data/DataScopeAccessDecision.php' => 'Foundation',
    'Data/ErrorDescriptor.php' => 'Foundation',
];

test('every core src file is classified into a zone or composition/testing', function (): void {
    expect(rz0Offenders())->toBe([])
        ->and(zoneOf('packages/core/src/PubSub/PubSubManager.php'))->toBe('Messaging')
        ->and(CORE_ZONE_ALLOWED['Foundation'])->toBe([])
        ->and(zoneOf('packages/core/src/Support/ViolationList.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Support/InvalidConfiguration.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Support/SecretRef.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Contracts/DescribesError.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Data/ErrorDescriptor.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Enums/ErrorCategory.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Enums/UnavailabilityReason.php'))->toBe('Foundation')
        ->and(coreZoneKind('packages/core/src/Support/CoreOptions.php'))->toBe('composition')
        ->and(coreZoneKind('packages/core/src/Support/PackageRegistrar.php'))->toBe('composition')
        ->and(coreZoneKind('packages/core/src/Support/RegistersNothingOnBoot.php'))->toBe('composition');
});

test('zone files import only allowed CoreX zones (R-Z1)', function (): void {
    expect(rz1Offenders())->toBe([]);
});

test('foreign files do not import zone Internal (R-Z2)', function (): void {
    expect(rz2Offenders())->toBe([]);
});

test('R-Z1 planting in Foundation RecordRef names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Support/Data/RecordRef.php',
        'CoreX\\Views\\Internal\\SqlSavedViewRepository',
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('RecordRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z1 planting in TenancyContracts AccountRef names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Tenancy/AccountRef.php',
        'CoreX\\Views\\Internal\\SqlSavedViewRepository',
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('AccountRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z2 planting in ActorType names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Enums/ActorType.php',
        'CoreX\\Views\\Internal\\SqlSavedViewRepository',
        rz2Offenders(...),
    );

    expect($line)->toContain('R-Z2')
        ->and($line)->toContain('ActorType.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z0 planting of an unclassified error file names the path', function (): void {
    $relative = 'packages/core/src/Data/UnclassifiedError.php';
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Data;

final class UnclassifiedError
{
}
PHP;

    file_put_contents($absolute, $contents);

    try {
        $hits = array_values(array_filter(
            rz0Offenders(),
            static fn (string $line): bool => str_contains($line, 'Data/UnclassifiedError.php'),
        ));

        expect($hits)->not->toBe([])
            ->and($hits[0])->toContain('R-Z0')
            ->and($hits[0])->toContain('Data/UnclassifiedError.php');
    } finally {
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }
});

test('R-Z0 planting of an unclassified Support file names the file', function (): void {
    $relative = 'packages/core/src/Support/Probe.php';
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Support;

final class Probe
{
}
PHP;

    file_put_contents($absolute, $contents);

    try {
        $hits = array_values(array_filter(
            rz0Offenders(),
            static fn (string $line): bool => str_contains($line, 'Support/Probe.php'),
        ));

        expect($hits)->not->toBe([])
            ->and($hits[0])->toContain('R-Z0')
            ->and($hits[0])->toContain('Probe.php');
    } finally {
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }
});

/**
 * @return list<string>
 */
function corePhpFiles(): array
{
    $root = SourceScanner::packagePath('core', 'src');
    $prefix = SourceScanner::repoRoot().'/';
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $files[] = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    sort($files);

    return $files;
}

function zoneOf(string $relative): ?string
{
    $kind = coreZoneKind($relative);

    if ($kind === 'testing' || $kind === 'composition' || $kind === 'unclassified') {
        return null;
    }

    return $kind;
}

function coreZoneKind(string $relative): string
{
    $prefix = 'packages/core/src/';

    if (! str_starts_with($relative, $prefix)) {
        return 'unclassified';
    }

    $under = substr($relative, strlen($prefix));

    if (str_starts_with($under, 'Testing/')) {
        return 'testing';
    }

    if (in_array($relative, CORE_ZONE_COMPOSITION, true)) {
        return 'composition';
    }

    if (in_array($under, CORE_ZONE_FOUNDATION_SUPPORT, true)) {
        return 'Foundation';
    }

    foreach (CORE_ZONE_PREFIX as $pathPrefix => $zone) {
        if (str_starts_with($under, $pathPrefix)) {
            return $zone;
        }
    }

    return CORE_ZONE_DUMP[$under] ?? 'unclassified';
}

function coreZoneIsExempt(string $relative): bool
{
    $kind = coreZoneKind($relative);

    return $kind === 'testing' || $kind === 'composition';
}

/**
 * @return list<string>
 */
function rz0Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        if (coreZoneIsExempt($relative)) {
            continue;
        }

        if (zoneOf($relative) === null) {
            $offenders[] = 'R-Z0 '.$relative.' unclassified';
        }
    }

    return $offenders;
}

/**
 * @return list<string>
 */
function rz1Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        $sourceZone = zoneOf($relative);

        if ($sourceZone === null) {
            continue;
        }

        $absolute = SourceScanner::repoRoot().'/'.$relative;

        foreach (coreZoneCoreXImports($absolute) as $import) {
            $target = coreZoneImportTarget($import);

            if ($target === null) {
                continue;
            }

            $targetKind = coreZoneKind($target);

            if ($targetKind === 'composition' || $targetKind === 'testing') {
                continue;
            }

            if (coreZoneExactImport($relative, $import)) {
                continue;
            }

            if ($targetKind === 'unclassified' || ! coreZoneImportAllowed($relative, $sourceZone, $targetKind)) {
                $offenders[] = 'R-Z1 '.$relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

function coreZoneExactImport(string $relative, string $import): bool
{
    return in_array($import, CORE_ZONE_EXACT_IMPORTS[$relative] ?? [], true);
}

function coreZoneImportAllowed(string $relative, string $sourceZone, string $targetKind): bool
{
    if ($targetKind === $sourceZone) {
        return true;
    }

    if ($relative === CORE_ZONE_E_BASEMODEL && $targetKind === 'Audit') {
        return true;
    }

    return in_array($targetKind, CORE_ZONE_ALLOWED[$sourceZone], true);
}

/**
 * @return list<string>
 */
function rz2Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        if (coreZoneKind($relative) === 'composition') {
            continue;
        }

        $absolute = SourceScanner::repoRoot().'/'.$relative;

        foreach (coreZoneCoreXImports($absolute) as $import) {
            if (preg_match('/^CoreX\\\\([^\\\\]+)\\\\Internal\\\\([^\\\\]+)$/', $import) !== 1) {
                continue;
            }

            $target = coreZoneImportTarget($import);
            $zone = $target === null ? 'unclassified' : coreZoneKind($target);

            if ($zone === 'unclassified' || ! coreZoneFileInZoneDirectories($relative, $zone)) {
                $offenders[] = 'R-Z2 '.$relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

function coreZoneFileInZoneDirectories(string $relative, string $zone): bool
{
    $directories = match ($zone) {
        'Messaging' => ['Messaging', 'PubSub'],
        'SavedViews' => ['Views'],
        'TenancyContracts' => ['Tenancy'],
        'Foundation' => [
            'Support', 'PHPStan', 'Data', 'Concerns', 'Contracts', 'Models', 'Enums', 'Events', 'Exceptions',
        ],
        'Audit', 'Departments', 'Features', 'Money', 'Settings', 'Storage' => [$zone],
        default => [],
    };

    $under = substr($relative, strlen('packages/core/src/'));

    foreach ($directories as $directory) {
        if (str_starts_with($under, $directory.'/')) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function coreZoneCoreXImports(string $absolute): array
{
    $contents = (string) file_get_contents($absolute);
    preg_match_all('/^\s*use\s+(?:function\s+|const\s+)?(CoreX\\\\[^;]+);/m', $contents, $matches);

    $imports = [];

    foreach ($matches[1] as $import) {
        $imports[] = trim(explode(' as ', $import, 2)[0]);
    }

    return $imports;
}

function coreZoneImportTarget(string $import): ?string
{
    if (! str_starts_with($import, 'CoreX\\')) {
        return null;
    }

    $rest = substr($import, strlen('CoreX\\'));

    return 'packages/core/src/'.str_replace('\\', '/', $rest).'.php';
}

/**
 * Insert one CoreX import, collect the matching diagnostic, then restore the file.
 *
 * @param  callable(): list<string>  $collect
 */
function coreZonePlant(string $relative, string $import, callable $collect): string
{
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $original = (string) file_get_contents($absolute);
    $needle = "declare(strict_types=1);\n";
    $count = 0;
    $planted = str_replace($needle, $needle."\nuse {$import};\n", $original, $count);

    if ($count !== 1) {
        throw new RuntimeException('R-Z planting could not insert a use into '.$relative);
    }

    file_put_contents($absolute, $planted);

    try {
        $symbol = substr($import, strrpos($import, '\\') + 1);
        $hits = array_values(array_filter(
            $collect(),
            static fn (string $line): bool => str_contains($line, basename($relative))
                && str_contains($line, $symbol),
        ));

        expect($hits)->not->toBe([]);

        return $hits[0];
    } finally {
        file_put_contents($absolute, $original);
    }
}
