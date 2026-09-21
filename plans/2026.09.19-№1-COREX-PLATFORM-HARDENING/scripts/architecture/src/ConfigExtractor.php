<?php

declare(strict_types=1);

// P2.4 — Извлечение реестра ключей конфигурации.
// Проход 1: литералы из packages/<pkg>/config/*.php (вложенные ключи через точку).
// Проход 2: литералы config('...') из files.csv колонки config_reads.
// Ключ без объявления → notes: undeclared-read.
final class ConfigExtractor
{
    public const CONFIG_HEADERS = [
        'key', 'owner', 'type', 'default', 'secret', 'mode',
        'reloadable', 'validation', 'typed_options_field', 'deprecated_aliases',
    ];

    private const SECRET_SEGMENTS = ['password', 'secret', 'token', 'key', 'api_key'];

    private const SECRET_EXCEPTIONS = ['strategy', 'prefix', 'store', 'connection', 'tag', 'kind'];

    /** @var array<string, array<string, string>> */
    private array $declared = [];

    /** @var array<string, string> key → ownerPkg */
    private array $undeclaredReads = [];

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $planRoot,
    ) {}

    /** @return list<array<string, string>> */
    public function extract(): array
    {
        $this->scanConfigFiles();
        $this->mergeFileCsvReads();

        $rows = array_values($this->declared);
        foreach ($this->undeclaredReads as $key => $owner) {
            $rows[] = $this->makeUndeclaredRow($key, $owner);
        }

        usort($rows, static fn (array $a, array $b) => $a['key'] <=> $b['key']);

        return $rows;
    }

    private function scanConfigFiles(): void
    {
        $configs = glob($this->repoRoot.'/packages/*/config/*.php') ?: [];
        sort($configs);

        foreach ($configs as $path) {
            $fileKey = basename($path, '.php');
            $pkgDir = dirname(dirname($path));
            $owner = $this->packageName($pkgDir);
            $src = file_get_contents($path);

            if ($src === false) {
                continue;
            }
            $pairs = $this->extractKeyValuePairs($src, $fileKey);
            foreach ($pairs as [$canonKey, $type, $default, $fromEnv]) {
                $secret = $this->isSecret($canonKey);
                $this->declared[$canonKey] = [
                    'key' => $canonKey,
                    'owner' => $owner,
                    'type' => $type,
                    'default' => $secret && $default !== 'unobserved' ? '***' : $default,
                    'secret' => $secret ? 'yes' : 'no',
                    'mode' => $this->resolveMode($secret, $fromEnv),
                    'reloadable' => $fromEnv ? 'no' : 'unobserved',
                    'validation' => 'unobserved',
                    'typed_options_field' => '—',
                    'deprecated_aliases' => '',
                ];

                // CFG-006 для corex-intl-search
                if (str_starts_with($canonKey, 'corex-intl-search.')) {
                    $this->declared[$canonKey]['notes'] =
                        'CFG-006 exception; tombstone after major of search-intl';
                }
            }
        }
    }

    /**
     * Извлечь все пары (canonical_key, type, default, fromEnv) из PHP-конфига.
     * Использует token_get_all + стек для вложенных массивов.
     *
     * @return list<array{string, string, string, bool}>
     */
    private function extractKeyValuePairs(string $src, string $rootKey): array
    {
        $tokens = token_get_all($src, TOKEN_PARSE);
        $n = count($tokens);
        $result = [];

        // Состояние: стек ключей (для вложенных массивов)
        $keyStack = []; // list of strings — текущий путь
        // Ожидаем паттерн: T_CONSTANT_ENCAPSED_STRING T_DOUBLE_ARROW <value>
        // Обрабатываем скобки [ и ] для отслеживания глубины

        // Позиция после "return"
        $i = 0;
        // Пропустить до первой открытой скобки верхнего уровня
        while ($i < $n && $tokens[$i] !== '[' && ! (is_array($tokens[$i]) && $tokens[$i][0] === T_ARRAY)) {
            $i++;
        }
        // Теперь парсим массив
        $this->parseArray($tokens, $i, $n, [$rootKey], $result);

        return $result;
    }

    /**
     * @param  list<array{string, string, string, bool}>  $result
     */
    private function parseArray(
        array $tokens,
        int &$i,
        int $n,
        array $keyPath,
        array &$result,
    ): void {
        // Пропустить [ или array(
        if ($i < $n && ($tokens[$i] === '[' || (is_array($tokens[$i]) && $tokens[$i][0] === T_ARRAY))) {
            $i++;

            if ($i < $n && $tokens[$i] === '(') {
                $i++; // array(
            }
        }

        while ($i < $n) {
            $tok = $tokens[$i];

            // Конец массива
            if ($tok === ']' || $tok === ')') {
                $i++;

                return;
            }

            // Whitespace и запятые — пропустить
            if ($tok === ',' || (is_array($tok) && $tok[0] === T_WHITESPACE)) {
                $i++;

                continue;
            }

            // Строковый ключ T_CONSTANT_ENCAPSED_STRING => value
            if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                $keyStr = trim($tok[1], "'\"");
                $i++;
                // Пропустить whitespace
                while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }

                // Проверить =>
                if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_DOUBLE_ARROW) {
                    $i++;
                    while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                        $i++;
                    }

                    // Следующий токен — значение
                    if ($i >= $n) {
                        break;
                    }
                    $valueTok = $tokens[$i];
                    $newPath = [...$keyPath, $keyStr];

                    if ($valueTok === '[' || (is_array($valueTok) && $valueTok[0] === T_ARRAY)) {
                        // Вложенный массив — рекурсия
                        $this->parseArray($tokens, $i, $n, $newPath, $result);
                    } else {
                        // Скалярное значение
                        [$type, $default, $fromEnv] = $this->extractScalar($tokens, $i, $n);
                        $canonKey = implode('.', $newPath);
                        $result[] = [$canonKey, $type, $default, $fromEnv];
                    }
                } else {
                    // Нет =>, это не пара ключ=>значение (не должно быть в конфигах)
                    // Просто пропустить
                }

                continue;
            }

            // Пропустить всё остальное (целые числа как ключи, etc.)
            $i++;
        }
    }

    /**
     * Извлечь скалярное значение начиная с $i.
     *
     * @return array{string, string, bool} [type, default, fromEnv]
     */
    private function extractScalar(array $tokens, int &$i, int $n): array
    {
        $tok = $tokens[$i];

        // env('NAME', default)
        if (is_array($tok) && $tok[0] === T_STRING && $tok[1] === 'env') {
            $i++;
            // Пропустить (
            while ($i < $n && $tokens[$i] !== '(') {
                $i++;
            }
            $i++; // (
            // Первый аргумент — имя env
            while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                $i++;
            }
            // Пропустить ,
            while ($i < $n && ($tokens[$i] === ',' || (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE))) {
                $i++;
            }
            // Default значение
            $default = 'unobserved';

            if ($i < $n && $tokens[$i] !== ')') {
                [$default, $type2, $_] = $this->readOneValue($tokens, $i, $n);
            }
            // Пропустить до )
            while ($i < $n && $tokens[$i] !== ')') {
                $i++;
            }
            $i++; // )
            $type = $this->guessType($default);

            return [$type, $default, true];
        }

        // (bool) cast перед env(
        if (is_array($tok) && $tok[0] === T_BOOL_CAST) {
            $i++;
            while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            // Теперь должен быть env(...)
            if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && $tokens[$i][1] === 'env') {
                [$_, $default, $_2] = $this->extractScalar($tokens, $i, $n);

                return ['bool', $default, true];
            }
        }

        // (int) cast
        if (is_array($tok) && $tok[0] === T_INT_CAST) {
            $i++;
            while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && $tokens[$i][1] === 'env') {
                [$_, $default, $_2] = $this->extractScalar($tokens, $i, $n);

                return ['int', $default, true];
            }
        }

        // (string) cast
        if (is_array($tok) && $tok[0] === T_STRING_CAST) {
            $i++;
            while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && $tokens[$i][1] === 'env') {
                [$_, $default, $_2] = $this->extractScalar($tokens, $i, $n);

                return ['string', $default, true];
            }
        }

        [$default, $type, $fromEnv] = $this->readOneValue($tokens, $i, $n);

        return [$type, $default, $fromEnv];
    }

    /**
     * @return array{string, string, bool} [value, type, fromEnv]
     */
    private function readOneValue(array $tokens, int &$i, int $n): array
    {
        if ($i >= $n) {
            return ['unobserved', 'unobserved', false];
        }
        $tok = $tokens[$i];

        if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
            $val = trim($tok[1], "'\"");
            $i++;

            return [$val, 'string', false];
        }

        if (is_array($tok) && $tok[0] === T_LNUMBER) {
            $i++;

            return [$tok[1], 'int', false];
        }

        if (is_array($tok) && $tok[0] === T_DNUMBER) {
            $i++;

            return [$tok[1], 'float', false];
        }

        if (is_array($tok) && $tok[0] === T_STRING) {
            $val = $tok[1];

            if ($val === 'true') {
                $i++;

                return ['true', 'bool', false];
            }

            if ($val === 'false') {
                $i++;

                return ['false', 'bool', false];
            }

            if ($val === 'null') {
                $i++;

                return ['null', 'null', false];
            }
            // Может быть ClassName::class или Enum::Value
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }

            if ($j < $n && $tokens[$j] === '::') {
                $j++;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }

                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_CLASS_C) {
                    $i = $j + 1;

                    return [$val.'::class', 'class-string', false];
                }

                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $enumVal = $val.'::'.$tokens[$j][1];
                    $i = $j + 1;

                    return [$enumVal, 'enum', false];
                }
            }
        }

        // Унарный минус перед числом
        if ($tok === '-') {
            $i++;
            while ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }

            if ($i < $n && is_array($tokens[$i]) && $tokens[$i][0] === T_LNUMBER) {
                $val = '-'.$tokens[$i][1];
                $i++;

                return [$val, 'int', false];
            }
        }

        // Всё остальное (выражения, вызовы функций) — пропустить
        $i = $this->skipToCommaOrBracket($tokens, $i, $n);

        return ['unobserved', 'unobserved', false];
    }

    private function skipToCommaOrBracket(array $tokens, int $i, int $n): int
    {
        $depth = 0;
        while ($i < $n) {
            $tok = $tokens[$i];

            if (in_array($tok, ['(', '['], true)) {
                $depth++;
            } elseif (in_array($tok, [')', ']'], true)) {
                if ($depth === 0) {
                    return $i;
                }
                $depth--;
            } elseif ($tok === ',' && $depth === 0) {
                return $i;
            }
            $i++;
        }

        return $i;
    }

    private function guessType(string $default): string
    {
        if ($default === 'true' || $default === 'false') {
            return 'bool';
        }

        if ($default === 'null') {
            return 'null';
        }

        if (is_numeric($default)) {
            return 'int';
        }

        if ($default === 'unobserved') {
            return 'unobserved';
        }

        return 'string';
    }

    private function isSecret(string $canonKey): bool
    {
        $segments = explode('.', $canonKey);
        $lastSeg = strtolower(end($segments));
        foreach (self::SECRET_EXCEPTIONS as $exc) {
            if (str_contains($lastSeg, $exc)) {
                return false;
            }
        }
        foreach (self::SECRET_SEGMENTS as $seg) {
            if (str_contains($lastSeg, $seg)) {
                return true;
            }
        }

        return false;
    }

    private function resolveMode(bool $secret, bool $fromEnv): string
    {
        if ($secret) {
            return 'secret';
        }

        if ($fromEnv) {
            return 'deployment';
        }

        return 'product-policy';
    }

    private function makeUndeclaredRow(string $key, string $owner): array
    {
        $secret = $this->isSecret($key);

        return [
            'key' => $key,
            'owner' => $owner,
            'type' => 'unobserved',
            'default' => 'unobserved',
            'secret' => $secret ? 'yes' : 'no',
            'mode' => $secret ? 'secret' : 'product-policy',
            'reloadable' => 'unobserved',
            'validation' => 'use-site',
            'typed_options_field' => '—',
            'deprecated_aliases' => '',
            'notes' => 'undeclared-read',
        ];
    }

    private function mergeFileCsvReads(): void
    {
        $csvPath = $this->planRoot.'/artifacts/architecture/files.csv';

        if (! file_exists($csvPath)) {
            return;
        }
        $fh = fopen($csvPath, 'r');

        if ($fh === false) {
            return;
        }
        $headers = fgetcsv($fh);

        if ($headers === false) {
            fclose($fh);

            return;
        }
        $headers = array_map('trim', $headers);
        $pkgIdx = array_search('package', $headers, true);
        $readsIdx = array_search('config_reads', $headers, true);

        if ($pkgIdx === false || $readsIdx === false) {
            fclose($fh);

            return;
        }

        while (($row = fgetcsv($fh)) !== false) {
            $pkg = $row[$pkgIdx] ?? '';
            $reads = $row[$readsIdx] ?? '';

            if ($reads === '') {
                continue;
            }
            foreach (explode(';', $reads) as $key) {
                $key = trim($key);

                if ($key === '' || str_contains($key, '$') || str_contains($key, '{')) {
                    continue;
                }

                if (isset($this->declared[$key])) {
                    // Обновить validation на use-site если читается не из ServiceProvider
                    if ($this->declared[$key]['validation'] === 'unobserved') {
                        $this->declared[$key]['validation'] = 'use-site';
                    }
                } elseif (! isset($this->undeclaredReads[$key])) {
                    $this->undeclaredReads[$key] = $pkg;
                }
            }
        }
        fclose($fh);
    }

    private function packageName(string $pkgDir): string
    {
        $file = $pkgDir.'/composer.json';

        if (! file_exists($file)) {
            return basename($pkgDir);
        }
        $data = json_decode(file_get_contents($file) ?: '{}', true);

        return $data['name'] ?? basename($pkgDir);
    }
}
