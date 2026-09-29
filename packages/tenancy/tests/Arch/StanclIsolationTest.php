<?php

declare(strict_types=1);

// B-11 §7.3 п.1 / AC-29 — `Stancl\*` живёт только в axiomasoft/corex-tenancy.
//
// Второй, независимый от PHPStan носитель того же инварианта (ТЗ P2.13:
// «PHPStan-правило + arch-тест Pest в CI»). Правило работает по путям из
// корневого phpstan.neon, этот тест — по дереву исходников: если кто-то
// добавит пакет и забудет добавить его в `paths`, инвариант всё равно
// проверяется.
//
// **Почему НЕ `expect('CoreX')->not->toUse('Stancl')` (pest-plugin-arch).**
// Проверено эмпирически 2026-07-27 в ЭТОМ пакете: `expect(<FQCN>)->toUse(<FQCN>)`
// работает (зелёный на реальной зависимости `TenancyServiceProvider` →
// `Stancl\Tenancy\Events\TenantCreated`), но цель-НЕЙМСПЕЙС `'Stancl'` не
// раскрывается — `expect('CoreX\Tenancy')->not->toUse('Stancl')` ЗЕЛЁНЫЙ на
// дереве, где `packages/tenancy/src` пронизан импортами Stancl. Вакуумное
// правило хуже отсутствующего (тот же вывод, что D84/P3.5 сделал для
// `tests/Arch/LayerTest.php` на корневом сьюте). Сканирование исходников даёт
// тот же инвариант детерминированно и краснеет доказуемо (самопроверки ниже).

$packageRoot = \dirname(__DIR__, 2);
$repoRoot = \dirname($packageRoot, 2);

/**
 * Ссылки на `Stancl\*` в КОДЕ файла: `use Stancl\…` (в т.ч. групповые) и
 * полностью-квалифицированные `\Stancl\…`. Комментарии и докблоки вырезаются
 * токенайзером — упоминание вендора в докблоке контракта ядра легально
 * (`packages/core/src/Context/Contracts/TenantDatabaseLifecycle.php`) и
 * ловиться не должно. Ровно та же семантика, что у `StanclImportBoundaryRule`,
 * только на токенах вместо AST.
 *
 * @return list<string> строки вида `<файл>:<строка>: <фрагмент>`
 */
$stanclCodeReferences = static function (string $file): array {
    $source = (string) file_get_contents($file);
    $hits = [];

    foreach (token_get_all($source) as $token) {
        if (! \is_array($token)) {
            continue;
        }

        [$id, $text, $line] = $token;

        if (\in_array($id, [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], strict: true)) {
            continue;
        }

        if ($id !== T_NAME_QUALIFIED && $id !== T_NAME_FULLY_QUALIFIED) {
            continue;
        }

        if (explode('\\', ltrim($text, '\\'))[0] === 'Stancl') {
            $hits[] = $file.':'.$line.': '.$text;
        }
    }

    return $hits;
};

/**
 * `*.php` под $dir с вырезанным `vendor/` (установленный артефакт, не исходник).
 *
 * @return list<string>
 */
$phpFiles = static function (string $dir): array {
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $current): bool => ! $current->isDir()
                || ! \in_array($current->getFilename(), ['vendor', 'node_modules'], strict: true),
        ),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
};

// (1) Анти-вакуум-самопроверка детектора: пока `packages/tenancy/src` реально
// пронизан Stancl-импортами, детектор ОБЯЗАН их видеть. Без этого теста весь
// файл мог бы зеленеть просто потому, что сканер ничего не находит нигде.
test('the stancl detector actually detects (positive control on axiomasoft/corex-tenancy itself)', function () use ($packageRoot, $phpFiles, $stanclCodeReferences): void {
    $found = [];

    foreach ($phpFiles($packageRoot.'/src') as $file) {
        $found = [...$found, ...$stanclCodeReferences($file)];
    }

    // Ровно тот класс ссылок, ради которого пакет и существует (D13).
    expect(\count($found))->toBeGreaterThan(10);
});

// (2) Комментарии не считаются: контракт ядра НАЗЫВАЕТ вендора в докблоке и
// обязан оставаться зелёным (иначе гейт заставил бы врать документацию).
test('the stancl detector ignores comments and docblocks', function () use ($stanclCodeReferences): void {
    $fixture = tempnam(sys_get_temp_dir(), 'stancl-arch-').'.php';

    file_put_contents($fixture, <<<'PHP'
    <?php

    /** Mentions Stancl\Tenancy\Tenancy in a docblock — legal. */
    final class DocblockOnly
    {
        // Stancl\Tenancy in a line comment — legal.
        public function name(): string
        {
            return 'Stancl\Tenancy';
        }
    }
    PHP);

    $clean = $stanclCodeReferences($fixture);

    file_put_contents($fixture, <<<'PHP'
    <?php

    use Stancl\Tenancy\Tenancy;

    final class RealImport
    {
        public function t(): string
        {
            return Tenancy::class.\Stancl\Tenancy\Database\Models\Tenant::class;
        }
    }
    PHP);

    $dirty = $stanclCodeReferences($fixture);

    unlink($fixture);

    expect($clean)->toBe([])
        ->and(\count($dirty))->toBe(2);
});

// (3) Сам инвариант: ни один пакет, КРОМЕ axiomasoft/corex-tenancy, не ссылается на
// `Stancl\*` в коде. Boxed-профиль не тянет stancl/tenancy вообще —
// импорт где-либо ещё ломает его сборку молча (D13, B-11 §7.3 п.1).
test('no package outside axiomasoft/corex-tenancy references Stancl in code', function () use ($repoRoot, $packageRoot, $phpFiles, $stanclCodeReferences): void {
    // Split-зеркало пакета несёт только его собственное дерево — соседей там
    // физически нет, и сканировать нечего. В монорепо они есть, и (1) выше
    // уже доказал, что сканер не пуст.
    if (! is_dir($repoRoot.'/packages/core/src')) {
        expect(is_dir($packageRoot.'/src'))->toBeTrue();

        return;
    }

    $scanned = [];
    $offenders = [];

    foreach ((array) glob($repoRoot.'/packages/*', GLOB_ONLYDIR) as $package) {
        if ($package === $packageRoot) {
            continue;
        }

        foreach (['src', 'database', 'config', 'tests'] as $subtree) {
            foreach ($phpFiles($package.'/'.$subtree) as $file) {
                $scanned[] = $file;
                $offenders = [...$offenders, ...$stanclCodeReferences($file)];
            }
        }
    }

    // Корневой харнесс монорепо — тот же инвариант (`tests/Feature/BoxedTenancyTest.php`
    // держит его в докблоке, а не в коде).
    foreach ($phpFiles($repoRoot.'/tests') as $file) {
        $scanned[] = $file;
        $offenders = [...$offenders, ...$stanclCodeReferences($file)];
    }

    // Второй анти-вакуум-контур: пустой список файлов дал бы зелёный гейт ни о чём.
    expect(\count($scanned))->toBeGreaterThan(50)
        ->and($offenders)->toBe([]);
});
