#!/usr/bin/env php
<?php

declare(strict_types=1);

const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const RX_CONFIG = "/mergeConfigFrom\\(\\s*[^,]+,\\s*['\"]([^'\"]+)['\"]/";
const RX_TAG = "/->publishes(?:Migrations)?\\(\\s*\\[.*?\\]\\s*,\\s*['\"]([^'\"]+)['\"]/s";
const RX_COMMAND = "/(?:protected\\s+\\\$signature\\s*=\\s*['\"]|#\\[AsCommand\\(\\s*(?:name:\\s*)?['\"])([a-z0-9:_-]+)/i";
const RX_ENV = "/\\benv\\(\\s*['\"]([A-Z0-9_]+)['\"]/";
const RX_TABLE = "/Schema::(?:connection\\([^)]*\\)->)?create\\(\\s*['\"]([a-z0-9_]+)['\"]/";
const RX_NS = '/^\s*namespace\s+([\w\\\\]+)\s*[;{]/m';

$root = dirname(__DIR__);
$baselinePath = $root.'/scripts/ownership-gate-baseline.json';

try {
    $map = json_decode((string) file_get_contents($root.'/scripts/ownership-map.json'), true, flags: JSON_THROW_ON_ERROR);

    assert(is_array($map));
    assert(is_array($map['packages']) && is_array($map['kernel']));
    foreach (['packages', 'root_symbols', 'dev_roots', 'modules'] as $field) {
        assert(is_array($map['kernel'][$field]));
        foreach ($map['kernel'][$field] as $name) {
            assert(is_string($name));
        }
    }

    if ($map['schema'] !== 'corex-ownership-map/1') {
        throw new RuntimeException('invalid ownership map schema');
    }
    $plannedSources = [];
    $plannedDeclarations = $map['planned_identifier_sources'] ?? [];

    if (! is_array($plannedDeclarations)) {
        throw new RuntimeException('invalid planned identifier declarations');
    }
    foreach ($plannedDeclarations as $entry) {
        if (! is_array($entry) || ! is_string($entry['package'] ?? null) || $entry['package'] === ''
            || ! in_array($entry['kind'] ?? '', ['namespace', 'config', 'tag', 'command'], true)
            || ! is_string($entry['id'] ?? null) || $entry['id'] === ''
            || ! is_array($entry['source_files'] ?? null) || $entry['source_files'] === []) {
            throw new RuntimeException('invalid planned identifier source');
        }
        $sourceFiles = [];
        foreach ($entry['source_files'] as $sourceFile) {
            if (! is_string($sourceFile) || ! str_starts_with($sourceFile, 'packages/')
                || str_contains($sourceFile, '..') || ! str_ends_with($sourceFile, '.php')) {
                throw new RuntimeException('invalid planned identifier source file');
            }
            $sourceFiles[] = $sourceFile;
        }
        $plannedSources[$entry['package']][$entry['kind']][$entry['id']] = $sourceFiles;
    }
    $catalog = [];
    foreach ((($lines = file($root.'/docs/architecture/table-ownership.md')) === false ? throw new RuntimeException('Unreadable table catalog') : $lines) as $line) {
        if (preg_match('/^\| ([^|]+) \| ([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*) \|/', $line, $m) === 1) {
            $catalog[trim($m[1])] = $m[2];
        }
    }
    /** @var array<string, array<string, array<string, list<string>>>> $sourceInventory */
    $sourceInventory = [];
    $inventory = [];
    $violations = [];
    foreach ((($entries = glob($root.'/packages/*/composer.json')) === false ? [] : $entries) as $manifest) {
        $composer = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($composer) && is_string($composer['name']));
        $package = $composer['name'];
        $rule = $map['packages'][$package] ?? null;

        if (! is_array($rule) || $rule['dir'] !== basename(dirname($manifest))) {
            throw new RuntimeException('missing ownership map for '.$package);
        }
        assert(is_string($rule['root']));
        foreach (['config', 'tag_prefix', 'env_prefix', 'command_prefix'] as $field) {
            assert($rule[$field] === null || is_string($rule[$field]));
        }
        $identifiers = [];
        $sourceNamespaces = [];
        $identifierSources = [];
        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach (array_keys(is_array($composer[$section] ?? null) && is_array($composer[$section]['psr-4'] ?? null) ? $composer[$section]['psr-4'] : []) as $ns) {
                $identifiers['namespace'][rtrim((string) $ns, '\\')] = true;
            }
        }
        foreach (['src', 'config', 'database/migrations'] as $area) {
            $dir = dirname($manifest).'/'.$area;

            if (! is_dir($dir)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                // Preserve literals while excluding commented examples from the inventory.
                $source = '';
                foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                    $source .= is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $token[1]) : $token;
                }

                if ($area === 'src' && in_array($package, $map['kernel']['packages'], true)) {
                    // The root namespace admits only the glossary's closed root-symbol list.
                    $tokens = token_get_all($source);
                    $namespace = '';
                    foreach ($tokens as $position => $token) {
                        if (! is_array($token)) {
                            continue;
                        }

                        if ($token[0] === T_NAMESPACE) {
                            $namespace = '';
                            for ($i = $position + 1; isset($tokens[$i]) && ! in_array($tokens[$i], [';', '{'], true); $i++) {
                                if (is_array($tokens[$i]) && $tokens[$i][0] !== T_WHITESPACE) {
                                    $namespace .= $tokens[$i][1];
                                }
                            }
                        }

                        if ($namespace === 'CoreX' && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                            $i = $position + 1;
                            while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                                $i++;
                            }

                            if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                                $symbol = 'CoreX\\'.$tokens[$i][1];

                                if (! in_array($symbol, $map['kernel']['root_symbols'], true)) {
                                    $violations[] = "O3 symbol {$package} {$symbol} not-a-root-symbol";
                                }
                            }
                        }
                    }
                }
                $patterns = match ($area) {
                    'src' => ['namespace' => RX_NS, 'config' => RX_CONFIG, 'tag' => RX_TAG, 'command' => RX_COMMAND],
                    'config' => ['env' => RX_ENV],
                    default => ['table' => RX_TABLE],
                };
                foreach ($patterns as $kind => $pattern) {
                    preg_match_all($pattern, $source, $matches);

                    if ($kind === 'tag') {
                        preg_match_all('/->publishes(?:Migrations)?\(\s*\[.*?\]\s*,\s*\[([^\]]*)\]/s', $source, $tagArrays);
                        foreach ($tagArrays[1] as $tags) {
                            preg_match_all('~[\'"]([^\'"]+)[\'"]~', $tags, $tagNames);
                            $matches[1] = array_merge($matches[1], $tagNames[1]);
                        }
                    }
                    foreach ($matches[1] as $id) {
                        if ($kind === 'namespace') {
                            $sourceNamespaces[$id] = true;
                        }
                        $identifiers[$kind][$id] = true;
                        $identifierSources[$kind][$id][] = substr($file->getPathname(), strlen($root) + 1);
                    }
                }
            }
        }
        foreach ($identifiers as $kind => $ids) {
            foreach (array_keys($ids) as $id) {
                $inventory[] = ['kind' => $kind, 'package' => $package, 'id' => $id];

                // A pre-transfer identifier expires when its exact source declarations disappear.
                $declaredSources = $identifierSources[$kind][$id] ?? [];
                $sourceInventory[$kind][$id][$package] = $declaredSources;
                $packageKey = $package;
                $allowedSources = $plannedSources[$packageKey][$kind][$id] ?? [];
                $planned = ! in_array('--strict', $argv, true) && $declaredSources !== []
                    && array_diff($declaredSources, $allowedSources) === [];

                if ($kind === 'table') {
                    if (($catalog[$id] ?? null) !== $package) {
                        $violations[] = "O4 table {$package} {$id} catalog-owner ".($catalog[$id] ?? 'missing');
                    }

                    continue;
                }

                if ($kind === 'namespace') {
                    $expected = $rule['root'];
                    $kernel = in_array($package, $map['kernel']['packages'], true);
                    $devRoots = array_filter($map['kernel']['dev_roots'], static fn (string $ns): bool => $kernel ? str_starts_with($ns, 'CoreX\\Testing\\') : str_starts_with($ns, $expected.'\\'));
                    $dev = ! isset($sourceNamespaces[$id]) && (str_starts_with($id, $expected.'\\Tests') || in_array($id.'\\', $devRoots, true));

                    if ($id !== $expected && ! str_starts_with($id, $expected.'\\') && ! $dev && ! $planned) {
                        $violations[] = "O2 namespace {$package} {$id} expected {$expected}";
                    }

                    if ($kernel && $id !== 'CoreX' && ! $dev) {
                        $segment = explode('\\', $id)[1] ?? '';

                        if (! in_array($segment, $map['kernel']['modules'], true)) {
                            $violations[] = "O3 namespace {$package} {$id} not-a-kernel-module";
                        }
                    }

                    continue;
                }

                $upstreamConfig = $rule['upstream_config'] ?? [];

                if (! is_array($upstreamConfig)) {
                    throw new RuntimeException('invalid upstream config for '.$packageKey);
                }

                if ($kind === 'config' && in_array($id, $upstreamConfig, true)) {
                    continue;
                }
                $field = ['config' => 'config', 'tag' => 'tag_prefix', 'env' => 'env_prefix', 'command' => 'command_prefix'][$kind];
                $expected = $rule[$field];
                assert($expected === null || is_string($expected));

                if (! $planned && ($expected === null || ($kind === 'config' ? $id !== $expected : ! str_starts_with($id, $expected)))) {
                    $violations[] = "O2 {$kind} {$package} {$id} expected".($kind === 'config' ? '' : '-prefix').' '.($expected ?? 'none');
                }
            }
        }
    }
    $owners = [];
    foreach ($inventory as $record) {
        $owners[$record['kind']][$record['id']][$record['package']] = $record['package'];
    }
    foreach ($owners as $kind => $ids) {
        foreach ($ids as $id => $packages) {
            $sharedTags = $map['projected_shared_tags'] ?? [];
            assert(is_array($sharedTags));
            $shared = $kind === 'tag' && ! in_array('--strict', $argv, true)
                ? ($sharedTags[$id] ?? []) : [];
            assert(is_array($shared));
            $sharedNames = [];
            foreach ($shared as $name) {
                assert(is_string($name));
                $sharedNames[] = $name;
            }
            $sourcesMatch = $sharedNames !== [] && array_diff(array_keys($packages), $sharedNames) === [];
            foreach (array_keys($packages) as $owner) {
                $expectedSources = $owner === 'axiomasoft/corex'
                    ? ['packages/core/src/CoreServiceProvider.php']
                    : ($plannedSources[$owner][$kind][$id] ?? []);

                if (($sourceInventory[$kind][$id][$owner] ?? []) !== $expectedSources) {
                    $sourcesMatch = false;
                }
            }

            if (count(array_unique($packages)) > 1 && ! $sourcesMatch) {
                $names = array_keys($packages);
                sort($names);
                $violations[] = "O1 {$kind} {$id} owners ".implode(',', $names);
            }
        }
    }
    usort($inventory, static fn (array $a, array $b): int => [$a['kind'], $a['package'], $a['id']] <=> [$b['kind'], $b['package'], $b['id']]);
    $violations = array_values(array_unique($violations));
    sort($violations);

    if (in_array('--report', $argv, true)) {
        echo json_encode(['identifiers' => $inventory, 'violations' => $violations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        exit(EXIT_OK);
    }
    $baseline = is_file($baselinePath) ? json_decode((string) file_get_contents($baselinePath), true, flags: JSON_THROW_ON_ERROR) : ['schema' => 'corex-ownership-baseline/1', 'violations' => []];

    if (! is_array($baseline) || $baseline['schema'] !== 'corex-ownership-baseline/1' || ! is_array($baseline['violations'])) {
        throw new RuntimeException('invalid ownership baseline');
    }

    foreach ($baseline['violations'] as $line) {
        assert(is_string($line));
    }

    if (in_array('--write-baseline', $argv, true)) {
        if (is_file($baselinePath) && array_diff($violations, $baseline['violations']) !== []) {
            throw new RuntimeException('baseline may only lose entries after initial creation');
        }
        file_put_contents($baselinePath, json_encode(['schema' => 'corex-ownership-baseline/1', 'violations' => $violations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $baseline['violations'] = $violations;
    }
    $new = in_array('--strict', $argv, true) ? $violations : array_values(array_diff($violations, $baseline['violations']));
    $stale = array_values(array_diff($baseline['violations'], $violations));
    foreach (['new' => $new, 'stale' => $stale] as $kind => $diagnostics) {
        foreach ($diagnostics as $line) {
            fwrite(STDERR, "ownership FAIL: {$kind}: {$line}\n");
        }
    }

    if ($new !== [] || $stale !== []) {
        exit(EXIT_VIOLATION);
    }
    echo 'ownership OK: '.count($inventory).' identifiers, '.count($violations)." baselined\n";
    exit(EXIT_OK);
} catch (Throwable $error) {
    fwrite(STDERR, 'ownership CONFIG: '.$error->getMessage()."\n");
    exit(EXIT_CONFIG);
}
