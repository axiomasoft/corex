#!/usr/bin/env php
<?php

declare(strict_types=1);

use CoreX\Modules\Testing\ExtensionContract;

$root = dirname(__DIR__);
$fixture = ($argv[1] ?? null) === '--fixture' ? ($argv[2] ?? null) : null;

if (count($argv) > 1 && ($fixture === null || ! is_file($fixture) || ! str_ends_with($fixture, '.php'))) {
    fwrite(STDERR, "extension-contract-gate: --fixture requires a PHP file\n");
    exit(2);
}

/**
 * Every production *Registry, *Catalogue or *Manager is reviewed by identity.
 * A new candidate and a stale classification both require an explicit edit.
 * `runtime manager` records names that do not hold an extension catalogue.
 *
 * @var array<string, string>
 */
$classification = [
    'CoreX\\Modules\\Internal\\InMemoryEntityRegistry' => 'projection',
    'CoreX\\Errors\\Contracts\\ProblemRegistry' => 'boot-built catalogue',
    'CoreX\\Errors\\Internal\\CompiledProblemRegistry' => 'boot-built catalogue',
    'CoreX\\Operations\\Definition\\Contracts\\OperationRegistry' => 'projection',
    'CoreX\\Operations\\Pagination\\Contracts\\OrderingRegistry' => 'projection',
    'CoreX\\Search\\Contracts\\SearchDriverRegistry' => 'driver factory',
    'CoreX\\Search\\Internal\\ManagerDriverRegistry' => 'driver factory',
    'CoreX\\Modules\\Contracts\\FieldCastRegistry' => 'boot-built catalogue',
    'CoreX\\Webhooks\\Internal\\WebhookTopicCatalogue' => 'projection',
    'CoreX\\Tools\\Contracts\\ToolCatalogue' => 'projection',
    'CoreX\\Tools\\Internal\\CompiledToolCatalogue' => 'projection',
    'CoreX\Operations\Pagination\Internal\OrderingRegistry' => 'projection',
    'CoreX\Operations\Definition\Internal\OperationRegistry' => 'projection',
    'CoreX\\CustomFields\\Contracts\\FieldCatalogue' => 'data catalogue',
    'CoreX\\CustomFields\\Internal\\Integration\\AttributeMessageContractRegistry' => 'boot-built catalogue',
    'CoreX\\CustomFields\\Internal\\Schema\\CachedFieldCatalogue' => 'data catalogue',
    'CoreX\\CustomFields\\Internal\\Schema\\CompositeFieldCatalogue' => 'data catalogue',
    'CoreX\\CustomFields\\Internal\\Schema\\DatabaseFieldCatalogue' => 'data catalogue',
    'CoreX\\CustomFields\\Internal\\Schema\\ModuleFieldCatalogue' => 'projection',
    'CoreX\\CustomFields\\Internal\\Schema\\StaticFieldCatalogue' => 'data catalogue',
    'CoreX\\CustomFields\\Internal\\Values\\AttributeValueTransformerRegistry' => 'boot-built catalogue',
    'CoreX\\Messaging\\Contracts\\MessageContractRegistry' => 'authority',
    'CoreX\\Messaging\\Internal\\ArrayMessageContractRegistry' => 'authority',
    'CoreX\\Context\\Contracts\\TenancyManager' => 'runtime manager',
    'CoreX\Context\Internal\NullTenancyManager' => 'runtime manager',
    'CoreX\Modules\Data\CompiledRegistry' => 'authority',
    'CoreX\\Modules\\Contracts\\EntityRegistry' => 'projection',
    'CoreX\\Modules\\Contracts\\ModuleRegistry' => 'authority',
    'CoreX\Modules\Testing\ArrayEntityRegistry' => 'projection',
    'CoreX\\Modules\\Internal\\Registry\\CompiledModuleRegistry' => 'authority',
    'CoreX\\Panels\\Contracts\\PanelCatalogue' => 'boot-built catalogue',
    'CoreX\\Panels\\Internal\\ModulePanelCatalogue' => 'boot-built catalogue',
    'CoreX\Search\Internal\SearchManager' => 'driver factory',
    'CoreX\Tenancy\Provisioning\Internal\Database\TemplateClonePostgreSQLManager' => 'runtime manager',
    'CoreX\\Tenancy\\Runtime\\Internal\\DatabaseTenancyManager' => 'runtime manager',
    'CoreX\\Wakeup\\Internal\\WakeupHandlerRegistry' => 'authority',
];

$violations = [];
$found = [];
foreach (glob($root.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        foreach (registryCandidates((string) file_get_contents($file->getPathname())) as $candidate) {
            $found[$candidate] = true;

            if (! isset($classification[$candidate])) {
                $violations[] = "unclassified {$candidate} ({$file->getPathname()})";
            }
        }
    }
}

if ($fixture !== null) {
    foreach (registryCandidates((string) file_get_contents($fixture)) as $candidate) {
        if (! isset($classification[$candidate])) {
            $violations[] = "unclassified {$candidate} ({$fixture})";
        }
    }
}
foreach ($classification as $candidate => $kind) {
    if (! isset($found[$candidate])) {
        $violations[] = "stale classification {$candidate} ({$kind})";
    }
}

require $root.'/vendor/autoload.php';
require $root.'/tests/fixtures/extension-contract/fixture-adapter.php';
$fingerprints = [];
foreach (glob($root.'/tests/fixtures/extension-contract/*.json') ?: [] as $path) {
    $fixtureData = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    [$manifests, $versions] = extensionContractFixture($path);
    $report = ExtensionContract::verify(manifests: $manifests, installedPackages: $versions);
    $expected = $fixtureData['expect'] ?? null;

    if ($expected === 'pass' && $report->passed()) {
        $fingerprints[basename($path)] = $report->fingerprint;
    } elseif ($expected === 'fail' && ! $report->passed()) {
        foreach ($fixtureData['contains'] ?? [] as $needle) {
            if (! str_contains($report->summary(), $needle)) {
                $violations[] = basename($path).": missing diagnostic {$needle}";
            }
        }
    } else {
        $violations[] = basename($path).': unexpected contract result '.$report->summary();
    }
}

if ($violations !== []) {
    sort($violations);
    fwrite(STDERR, implode("\n", $violations)."\n");
    exit(1);
}

ksort($fingerprints);
fwrite(STDOUT, 'extension-contract-gate: OK ('.count($found)." classified)\n");
foreach ($fingerprints as $name => $fingerprint) {
    fwrite(STDOUT, "{$name}: {$fingerprint}\n");
}

/** @return list<string> */
function registryCandidates(string $source): array
{
    $tokens = token_get_all($source);
    $namespace = '';
    $candidates = [];
    foreach ($tokens as $index => $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $parts = [];
            for ($cursor = $index + 1; isset($tokens[$cursor]); $cursor++) {
                $part = $tokens[$cursor];

                if ($part === ';' || $part === '{') {
                    break;
                }

                if (is_array($part) && ! in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $parts[] = $part[1];
                }
            }
            $namespace = implode('', $parts);

            continue;
        }

        if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }
        $cursor = $index + 1;
        while (isset($tokens[$cursor]) && is_array($tokens[$cursor])
            && in_array($tokens[$cursor][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $cursor++;
        }
        $name = $tokens[$cursor] ?? null;

        if (! is_array($name) || $name[0] !== T_STRING
            || preg_match('/(?:Registry|Catalogue|Manager)$/', $name[1]) !== 1) {
            continue;
        }
        $candidates[] = ltrim($namespace.'\\'.$name[1], '\\');
    }

    return $candidates;
}
