<?php

declare(strict_types=1);
use CoreX\Tenancy\Internal\TenancySchemaSets;

// B-11 §7.3 п.2 / AC-29 — тенантские таблицы не ссылаются на root и наоборот.
//
// Cross-DB FK физически невозможен (root-БД и БД аккаунта — разные базы, D14),
// поэтому объявленный FK через границу не падает на код-ревью, а падает на
// провижининге аккаунта или, хуже, молча ломает клон golden-шаблона. Ловим
// статически.
//
// Плюс arch-тест «migration-path partition» (pre-mortem A4, Scope Included
// P2.13): файл миграции классифицируется root|tenant ПО ДИРЕКТОРИИ, множества
// путей central- и tenant-раннеров дизъюнктны, `corex:tenancy:migrate` грузит только
// tenant-пути — так root_-таблица в тенант-БД (и наоборот) невозможна по
// конструкции, а не по внимательности автора миграции.
//
// Разрешение имён — по строковым литералам и известным таблицам моделей.
// Любая динамическая цель требует ручного разбора и краснит гейт, если её нет
// в явном allowlist ниже. После D112 конфиг-driven целей в миграциях нет.

$packageRoot = \dirname(__DIR__, 2);
$repoRoot = \dirname($packageRoot, 2);

$registeredSets = [];
foreach ((new TenancySchemaSets)->sets() as $set) {
    $registeredSets[$set->name] = $set;
}
$rootLayerDir = $registeredSets['root']->path;
$tenantLayerDir = $registeredSets['tenant']->path;

/** Единственная директория миграций центральной (root) БД во всём репозитории. */
$isRootLayer = static fn (string $file): bool => str_contains(
    str_replace('\\', '/', $file),
    '/database/migrations/root/',
);

/**
 * `*.php` под $dir (рекурсивно), отсортированные.
 *
 * @return list<string>
 */
$migrationFiles = static function (string $dir): array {
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
};

/**
 * Цели FK, объявленные в исходнике: `->references('id')->on('X')` и
 * `->constrained('X')` (Laravel-формы). Возвращает пару списков: строковые
 * литералы и НЕразрешимые статически выражения.
 *
 * @return array{literal: list<string>, dynamic: list<string>}
 */
// Resolve only explicit imports with a plain literal model table in owning
// production PSR-4 source. No eval, model construction, query, or wildcard
// exception: unresolved/dynamic expressions still fail the inventory gate.
$literalModelTables = static function (string $source) use ($repoRoot): array {
    $prefixes = [];
    foreach (glob($repoRoot.'/packages/*/composer.json') ?: [] as $manifest) {
        $composer = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
        foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $paths) {
            foreach ((array) $paths as $path) {
                $prefixes[$prefix][] = dirname($manifest).'/'.$path;
            }
        }
    }
    preg_match_all('/^use ([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)(?: as ([a-zA-Z_][a-zA-Z0-9_]*))?;/m', $source, $imports, PREG_SET_ORDER);
    $tables = [];
    foreach ($imports as $import) {
        $class = $import[1];
        $alias = $import[2] ?? substr($class, (int) strrpos($class, '\\') + 1);
        foreach ($prefixes as $prefix => $directories) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }
            foreach ($directories as $directory) {
                $file = $directory.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (! is_file($file)) {
                    continue;
                }
                $model = (string) file_get_contents($file);

                if (! preg_match('/function\s+(?:getTable|__construct)\s*\(/', $model)
                    && preg_match('/protected\s+\$table\s*=\s*([\'"])([a-zA-Z_][a-zA-Z0-9_]*)\1\s*;/', $model, $table)) {
                    $tables[$alias] = $table[2];
                }
            }
        }
    }

    return $tables;
};

$foreignKeyTargets = static function (string $source) use ($literalModelTables): array {
    $literal = [];
    $dynamic = [];
    $modelTables = $literalModelTables($source);

    // Аргумент допускает ОДИН уровень вложенных скобок — этого хватает на
    // произвольное выражение с вызовом функции и не даёт регэксу оборваться на её `)`.
    $argument = '(?:[^(),]|\([^()]*\))*';

    preg_match_all(
        '/->on\(\s*('.$argument.')\s*[,)]|->constrained\(\s*('.$argument.')\s*[,)]/',
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    foreach ($matches as $match) {
        $target = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

        if ($target === '') {
            // `->constrained()` без аргумента — цель выводится Laravel'ом из
            // имени колонки; кросс-слойной она быть не может (в этом дереве
            // такой формы нет, но пропускать её молча нельзя).
            continue;
        }

        if (preg_match('/^([\'"])(.+)\1$/', $target, $quoted) === 1) {
            $literal[] = $quoted[2];

            continue;
        }

        if (preg_match('/^\(new ([a-zA-Z_][a-zA-Z0-9_]*)\)->getTable\(\)$/D', trim($target), $model) === 1 && isset($modelTables[$model[1]])) {
            $literal[] = $modelTables[$model[1]];

            continue;
        }

        $dynamic[] = $target;
    }

    return ['literal' => $literal, 'dynamic' => $dynamic];
};

/**
 * Таблицы, создаваемые миграцией: `Schema::create('X'` (только литералы —
 * конфиг-driven имена в axiomasoft/corex проверяются директорией, не именем).
 *
 * @return list<string>
 */
$createdTables = static function (string $source): array {
    preg_match_all('/(?:Schema::|\$this->)create\(\s*([\'"])(.+?)\1/', $source, $matches);

    return $matches[2];
};

// D112: shipped migrations use literal targets; no dynamic exceptions remain.
$allowedDynamicTargets = [];

/**
 * Все миграции репозитория (в split-зеркале — только собственные).
 *
 * @return list<string>
 */
$allMigrations = static function () use ($repoRoot, $packageRoot, $migrationFiles): array {
    $roots = is_dir($repoRoot.'/packages/core/database')
        ? (array) glob($repoRoot.'/packages/*/database/migrations', GLOB_ONLYDIR)
        : [$packageRoot.'/database/migrations'];

    $files = [];

    foreach ($roots as $dir) {
        $files = [...$files, ...$migrationFiles((string) $dir)];
    }

    sort($files);

    return $files;
};

// ---------------------------------------------------------------------------
// Анти-вакуум: детекторы обязаны детектировать.
// ---------------------------------------------------------------------------

test('the FK-target detector reads both Laravel forms and flags dynamic targets', function () use ($foreignKeyTargets): void {
    $sample = <<<'PHP'
    $table->foreign('account_id')->references('id')->on('root_accounts')->cascadeOnDelete();
    $table->foreignUuid('flag_id')->constrained($configuredTable)->cascadeOnDelete();
    $table->foreignUuid('stage_id')->constrained('crm_stages')->restrictOnDelete();
    PHP;

    expect($foreignKeyTargets($sample))->toBe([
        'literal' => ['root_accounts', 'crm_stages'],
        'dynamic' => ['$configuredTable'],
    ]);
});

test('literal model table resolution retains root detection and rejects unknown expressions', function () use ($foreignKeyTargets): void {
    $sample = <<<'PHP'
    use CoreX\Tenancy\Account\Internal\Models\Account;
    use CoreX\Tenancy\Workspace\Internal\Models\Workspace as Scope;
    $table->foreign('account_id')->references('id')->on((new Account)->getTable());
    $table->foreign('workspace_id')->constrained((new Scope)->getTable());
    $table->foreign('unknown_id')->constrained((new Unknown)->getTable());
    PHP;
    expect($foreignKeyTargets($sample))->toBe(['literal' => ['root_accounts', 'wsp_workspaces'], 'dynamic' => ['(new Unknown)->getTable()']]);
});

test('the migration inventory is not empty in either layer', function () use ($migrationFiles, $rootLayerDir, $tenantLayerDir, $allMigrations): void {
    // Пустой инвентарь дал бы зелёные гейты ниже, ничего не проверив.
    expect(\count($migrationFiles($rootLayerDir)))->toBeGreaterThan(10)
        ->and(\count($migrationFiles($tenantLayerDir)))->toBeGreaterThan(3)
        ->and(\count($allMigrations()))->toBeGreaterThan(\count($migrationFiles($rootLayerDir)));
});

// ---------------------------------------------------------------------------
// AC-29 (п.2) — нет FK через границу БД.
// ---------------------------------------------------------------------------

test('root-layer migrations declare foreign keys only to root_ tables', function () use ($migrationFiles, $rootLayerDir, $foreignKeyTargets): void {
    $offenders = [];
    $seen = 0;

    foreach ($migrationFiles($rootLayerDir) as $file) {
        $targets = $foreignKeyTargets((string) file_get_contents($file));

        foreach ($targets['literal'] as $target) {
            $seen++;

            if (! str_starts_with($target, 'root_')) {
                $offenders[] = basename($file).' → '.$target;
            }
        }

        foreach ($targets['dynamic'] as $target) {
            // В центральной БД конфиг-driven цель недопустима в принципе:
            // разрешить её означало бы разрешить FK на что угодно.
            $offenders[] = basename($file).' → (dynamic) '.$target;
        }
    }

    expect($seen)->toBeGreaterThan(10)
        ->and($offenders)->toBe([]);
});

test('tenant-layer migrations declare no foreign key to a root_ table', function () use ($allMigrations, $isRootLayer, $foreignKeyTargets, $allowedDynamicTargets): void {
    $offenders = [];
    $unknownDynamic = [];
    $seen = 0;

    foreach ($allMigrations() as $file) {
        if ($isRootLayer($file)) {
            continue;
        }

        $targets = $foreignKeyTargets((string) file_get_contents($file));

        foreach ($targets['literal'] as $target) {
            $seen++;

            if (str_starts_with($target, 'root_')) {
                $offenders[] = basename($file).' → '.$target;
            }
        }

        foreach ($targets['dynamic'] as $target) {
            if (! \in_array($target, $allowedDynamicTargets, strict: true)) {
                $unknownDynamic[] = basename($file).' → '.$target;
            }
        }
    }

    expect($seen)->toBeGreaterThan(5)
        ->and($offenders)->toBe([])
        ->and($unknownDynamic)->toBe([]);
});

// ---------------------------------------------------------------------------
// Pre-mortem A4 — migration-path partition.
// ---------------------------------------------------------------------------

test('every axiomasoft/corex-tenancy migration is classified root or tenant by its directory', function () use ($packageRoot, $migrationFiles, $rootLayerDir, $tenantLayerDir): void {
    $all = $migrationFiles($packageRoot.'/database/migrations');
    $root = $migrationFiles($rootLayerDir);
    $tenant = $migrationFiles($tenantLayerDir);

    // Тотальность классификации: файла вне двух директорий (например прямо в
    // `database/migrations/`) быть не может — такой поехал бы обоими раннерами.
    expect(array_values(array_diff($all, $root, $tenant)))->toBe([])
        ->and(array_values(array_intersect($root, $tenant)))->toBe([])
        ->and(\count($all))->toBe(\count($root) + \count($tenant));
});

test('root-directory migrations create only root_ tables and tenant-directory migrations create none', function () use ($migrationFiles, $rootLayerDir, $tenantLayerDir, $createdTables): void {
    $misplaced = [];
    $seen = 0;

    foreach ([$rootLayerDir => true, $tenantLayerDir => false] as $dir => $expectsRootPrefix) {
        foreach ($migrationFiles((string) $dir) as $file) {
            foreach ($createdTables((string) file_get_contents($file)) as $table) {
                $seen++;

                if (str_starts_with($table, 'root_') !== $expectsRootPrefix) {
                    $misplaced[] = basename($file).' → '.$table;
                }
            }
        }
    }

    expect($seen)->toBeGreaterThan(15)
        ->and($misplaced)->toBe([]);
});

test('the central and tenant runners use disjoint registered schema scopes', function () use ($packageRoot): void {
    $provider = (string) file_get_contents($packageRoot.'/src/TenancyServiceProvider.php');
    $sets = new TenancySchemaSets;
    $byName = [];
    foreach ($sets->sets() as $set) {
        $byName[$set->name] = $set->scope->value;
        expect($set->version)->toBe(1)->and($set->drivers)->toBe(['pgsql']);
    }
    expect($byName)->toBe(['root' => 'central', 'tenant' => 'tenant'])
        ->and(preg_match('/\$this->loadMigrationsFrom\s*\(/', $provider))->toBe(0);
    $orchestrator = (string) file_get_contents($packageRoot.'/src/Account/Laravel/MigrationWaveOrchestrator.php');
    expect($orchestrator)->toContain("\$this->schema->migrate(scope: SchemaScope::Tenant, connection: 'tenant')")
        ->not->toContain('database/migrations/root');
});
