#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\PackageDocs;

use CoreX\Attributes\Tooling\SurfaceReport;
use CoreX\Attributes\Tooling\SurfaceScanner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * P17.8 — README topic contract, PHP example compile, public-API import check.
 *
 * Does not rewrite package prose. A failing live package is assigned to its
 * producer phase in the JSON report.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

/** @var array<string, string> */
const PRODUCER_PHASE = [
    'axiomasoft/corex-access' => 'P7',
    'axiomasoft/corex-custom-fields' => 'P10',
    'axiomasoft/corex' => 'P6',
    'axiomasoft/corex-intl' => 'P16',
    'axiomasoft/corex-media' => 'P7',
    'axiomasoft/corex-notifications' => 'P5',
    'axiomasoft/corex-operations' => 'P13',
    'axiomasoft/corex-panels' => 'P11',
    'axiomasoft/corex-recurrence' => 'P4',
    'axiomasoft/corex-search' => 'P12',
    'axiomasoft/corex-search-intl' => 'P12',
    'axiomasoft/corex-search-meilisearch' => 'P12',
    'axiomasoft/corex-tasks' => 'P16',
    'axiomasoft/corex-tasks-workflow' => 'P4',
    'axiomasoft/corex-tenancy' => 'P9',
    'axiomasoft/corex-transfer' => 'P12',
    'axiomasoft/corex-wakeup' => 'P5',
    'axiomasoft/corex-workflow' => 'P11',
];

const TOPICS = ['installation', 'configuration', 'extension', 'failure'];

$repoRoot = dirname(__DIR__);
// The whole-monorepo AST/ownership graph exceeds PHP's 128 MiB CLI default.
ini_set('memory_limit', '1G');
require $repoRoot.'/vendor/autoload.php';
$argvList = array_slice($argv, 1);
$wantJson = in_array('--json', $argvList, true);
$pristine = in_array('--pristine', $argvList, true);

try {
    $report = runDocsGate($repoRoot, $pristine);
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'package-docs-gate: CONFIG '.$exception->getMessage()."\n");
    exit(EXIT_CONFIG);
}

if ($wantJson) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} else {
    fwrite(STDERR, formatReport($report));
}

exit($report['ok'] ? EXIT_OK : EXIT_VIOLATION);

/**
 * @return array<string, mixed>
 */
function runDocsGate(string $repoRoot, bool $pristine): array
{
    $cohort = loadCohort($repoRoot);
    $packages = [];
    $violations = [];

    foreach ($cohort as $package) {
        $result = inspectPackage($repoRoot, $package, $pristine);
        $packages[] = $result;
        foreach ($result['violations'] as $violation) {
            $violations[] = $violation;
        }
    }

    $ok = $violations === [];

    return [
        'schema_version' => 'corex-package-docs-gate/1',
        'ok' => $ok,
        'cohort_count' => count($cohort),
        'packages' => $packages,
        'violations' => $violations,
    ];
}

/**
 * @return list<array{name: string, path: string, dir: string, composer: array<string, mixed>}>
 */
function loadCohort(string $repoRoot): array
{
    $cohortPath = $repoRoot.'/scripts/package-docs-cohort.json';

    if (! is_file($cohortPath)) {
        throw new RuntimeException('missing release-cohort.json');
    }
    $decoded = json_decode((string) file_get_contents($cohortPath), true);

    if (! is_array($decoded) || ! isset($decoded['packages']) || ! is_array($decoded['packages'])) {
        throw new RuntimeException('invalid release-cohort.json');
    }

    $out = [];
    foreach ($decoded['packages'] as $row) {
        $name = (string) ($row['name'] ?? '');
        $path = (string) ($row['path'] ?? '');

        if ($name === '' || $path === '') {
            throw new RuntimeException('cohort row missing name/path');
        }
        $composerPath = $repoRoot.'/'.$path.'/composer.json';

        if (! is_file($composerPath)) {
            throw new RuntimeException('missing '.$composerPath);
        }
        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            throw new RuntimeException('invalid '.$composerPath);
        }
        $out[] = [
            'name' => $name,
            'path' => $path,
            'dir' => basename($path),
            'composer' => $composer,
        ];
    }
    usort($out, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

    return $out;
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @return array<string, mixed>
 */
function inspectPackage(string $repoRoot, array $package, bool $pristine): array
{
    $readmePath = $repoRoot.'/'.$package['path'].'/README.md';
    $violations = [];
    $topics = [];

    if (! is_file($readmePath)) {
        foreach (TOPICS as $topic) {
            $violations[] = violation($package, $readmePath, $topic, '', '', 'missing README');
            $topics[$topic] = 'missing';
        }

        return packageResult($package, $readmePath, $topics, [], $violations);
    }

    $markdown = (string) file_get_contents($readmePath);
    $headings = extractHeadings($markdown);
    $blocks = extractCodeBlocks($markdown);

    foreach (TOPICS as $topic) {
        $status = topicStatus($topic, $markdown, $headings, $package, $repoRoot);
        $topics[$topic] = $status['status'];

        if ($status['status'] === 'missing') {
            $violations[] = violation(
                $package,
                $readmePath,
                $topic,
                $status['heading'],
                '',
                $status['detail'],
            );
        }
    }

    $examples = [];
    foreach ($blocks as $index => $block) {
        if ($block['language'] !== 'php') {
            continue;
        }
        $example = inspectPhpBlock($repoRoot, $package, $readmePath, $index, $block, $pristine);
        $examples[] = $example;
        foreach ($example['violations'] as $violation) {
            $violations[] = $violation;
        }
    }

    return packageResult($package, $readmePath, $topics, $examples, $violations);
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @param  array<string, string>  $topics
 * @param  list<array<string, mixed>>  $examples
 * @param  list<array<string, mixed>>  $violations
 * @return array<string, mixed>
 */
function packageResult(array $package, string $readmePath, array $topics, array $examples, array $violations): array
{
    return [
        'package' => $package['name'],
        'path' => $package['path'],
        'readme' => $readmePath,
        'producer_phase' => PRODUCER_PHASE[$package['name']] ?? 'P1',
        'package_type' => (string) ($package['composer']['extra']['corex']['package-type'] ?? ''),
        'topics' => $topics,
        'examples' => $examples,
        'violations' => $violations,
    ];
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @return array<string, mixed>
 */
function violation(array $package, string $path, string $heading, string $matchedHeading, string $symbol, string $detail, string $codeBlock = ''): array
{
    return [
        'package' => $package['name'],
        'path' => $path,
        'heading' => $heading,
        'matched_heading' => $matchedHeading,
        'code_block' => $codeBlock,
        'symbol' => $symbol,
        'detail' => $detail,
        'producer_phase' => PRODUCER_PHASE[$package['name']] ?? 'P1',
    ];
}

/** @return list<string> */
function extractHeadings(string $markdown): array
{
    $headings = [];
    foreach (preg_split("/\R/", $markdown) ?: [] as $line) {
        if (preg_match('/^#{1,6}\s+(.+)$/', trim($line), $match) !== 1) {
            continue;
        }
        $headings[] = trim($match[1]);
    }

    return $headings;
}

/**
 * @return list<array{language: string, code: string}>
 */
function extractCodeBlocks(string $markdown): array
{
    $blocks = [];

    if (preg_match_all('/```([a-zA-Z0-9_-]*)\n(.*?)```/s', $markdown, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            $blocks[] = [
                'language' => strtolower($match[1]),
                'code' => $match[2],
            ];
        }
    }

    return $blocks;
}

/**
 * @param  list<string>  $headings
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @return array{status: string, heading: string, detail: string}
 */
function topicStatus(string $topic, string $markdown, array $headings, array $package, string $repoRoot): array
{
    $headingHit = matchingHeading($topic, $headings);

    if ($headingHit !== null) {
        return ['status' => 'heading', 'heading' => $headingHit, 'detail' => ''];
    }

    if (bodyCoversTopic($topic, $markdown)) {
        return ['status' => 'equivalent', 'heading' => '', 'detail' => ''];
    }

    $na = notApplicable($topic, $package, $repoRoot, $markdown);

    if ($na !== null) {
        return ['status' => 'not-applicable', 'heading' => '', 'detail' => $na];
    }

    return ['status' => 'missing', 'heading' => '', 'detail' => 'README has no '.$topic.' heading or declared equivalent'];
}

/** @param  list<string>  $headings */
function matchingHeading(string $topic, array $headings): ?string
{
    $patterns = [
        'installation' => '/install|установ|composer require|миграц/iu',
        'configuration' => '/config|настрой|defaults|option/iu',
        'extension' => '/extend|public api|contract|port|capabilities|seam|границ/iu',
        'failure' => '/fail|error|exception|ошиб|failure|validation|ограничен/iu',
    ];
    $pattern = $patterns[$topic];
    foreach ($headings as $heading) {
        if (preg_match($pattern, $heading) === 1) {
            return $heading;
        }
    }

    return null;
}

function bodyCoversTopic(string $topic, string $markdown): bool
{
    $patterns = [
        'installation' => '/composer require|vendor:publish|ставится как зависимость|php artisan migrate|loadMigrationsFrom|Install it with/i',
        'configuration' => '/config\(|corex-[a-z0-9-]+\.|Options|disabled by default|defaults to|конфиг-ключ|set `corex-|configured cadence|configured Ed25519|table names are configured/i',
        'extension' => '/\\\\Contracts\\\\|src\/Contracts|public API|публичные контракты|host обязан|consumer supplies|bind `|tagged `|PanelProvider|CreateTaskCommand|public-api|typed ports|extender|consumers must bind/i',
        'failure' => '/fail[- ]closed|fail closed|Exception|refuses|aborted|unsupported|invalid|quarantine|denied|denying|never blindly|останавливается|уникальности|не исключение|422|404|ломал|никогда не/i',
    ];

    return preg_match($patterns[$topic], $markdown) === 1;
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 */
function notApplicable(string $topic, array $package, string $repoRoot, string $markdown): ?string
{
    $packageType = (string) ($package['composer']['extra']['corex']['package-type'] ?? '');
    $configDir = $repoRoot.'/'.$package['path'].'/config';
    $configFiles = is_dir($configDir) ? (glob($configDir.'/*.php') ?: []) : [];

    if ($topic === 'configuration' && $configFiles === []) {
        return 'package-type='.$packageType.'; no config/*.php';
    }

    return null;
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @param  array{language: string, code: string}  $block
 * @return array<string, mixed>
 */
function inspectPhpBlock(string $repoRoot, array $package, string $readmePath, int $index, array $block, bool $pristine): array
{
    $wrapped = wrapPhpExample($block['code']);
    $signature = looksLikeSignature($block['code']);
    $tmp = tempnam(sys_get_temp_dir(), 'corex-docs-php-');

    if ($tmp === false) {
        throw new RuntimeException('cannot create temp php example');
    }
    $phpFile = $tmp.'.php';
    rename($tmp, $phpFile);
    file_put_contents($phpFile, $wrapped);
    $lint = [];
    $lintCode = 0;

    if ($signature) {
        $lintCode = 0;
        $lint = ['signature-not-executed'];
    } else {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($phpFile).' 2>&1', $lint, $lintCode);
    }
    unlink($phpFile);

    $violations = [];
    $label = 'php-block-'.$index;

    if ($lintCode !== 0) {
        $violations[] = violation(
            $package,
            $readmePath,
            'example',
            '',
            '',
            'php -l failed: '.implode(' ', $lint),
            $label,
        );
    }

    $symbols = extractCorexSymbols($wrapped);
    $foreignMaps = cohortPublicApiMaps($repoRoot);

    foreach ($symbols as $symbol) {
        if (str_contains($symbol, '\\Internal\\') || str_ends_with($symbol, '\\Internal')) {
            $violations[] = violation(
                $package,
                $readmePath,
                'example',
                '',
                $symbol,
                'example imports Internal namespace',
                $label,
            );

            continue;
        }

        if (! coveredByAnyPublicApi($symbol, $foreignMaps)) {
            $violations[] = violation(
                $package,
                $readmePath,
                'example',
                '',
                $symbol,
                'example symbol is outside source Api/Spi surface',
                $label,
            );
        }
    }

    $installed = null;

    if ($pristine && $symbols !== []) {
        $installed = compileAgainstArchive($repoRoot, $package, $symbols);
        foreach ($installed['missing'] as $symbol) {
            $violations[] = violation(
                $package,
                $readmePath,
                'example',
                '',
                $symbol,
                'symbol missing from installed archive public surface',
                $label,
            );
        }
    }

    return [
        'index' => $index,
        'lint' => $lintCode === 0 ? 'pass' : 'fail',
        'symbols' => $symbols,
        'pristine' => $installed,
        'violations' => $violations,
    ];
}

function looksLikeSignature(string $code): bool
{
    return preg_match('/\b[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s+\$[A-Za-z_]/', $code) === 1
        && preg_match('/\)\s*:\s*[A-Za-z_\\\\?]/', $code) === 1;
}

function wrapPhpExample(string $code): string
{
    $trimmed = trim($code);

    if (! str_starts_with($trimmed, '<?php')) {
        if (preg_match("/^'[^']+'\\s*=>/", $trimmed) === 1 || str_starts_with($trimmed, '[')) {
            $trimmed = "<?php\n\$example = [\n".$trimmed."\n];\n";
        } elseif (str_contains($trimmed, '$this->') && ! preg_match('/\bclass\b/', $trimmed)) {
            $trimmed = "<?php\nfinal class DocumentationExample {\n    public function example(): mixed {\n".$trimmed."\n        return null;\n    }\n}\n";
        } else {
            $trimmed = "<?php\n".$trimmed."\n";
        }
    }

    $withoutTag = trim(preg_replace('/^<\?php\s*/', '', $trimmed) ?? $trimmed);
    $body = rtrim($withoutTag);

    if (! str_ends_with($body, ';') && ! str_ends_with($body, '}')) {
        $trimmed = "<?php\n".$body.";\n";
    }

    return $trimmed;
}

/** @return list<string> */
function extractCorexSymbols(string $code): array
{
    $tokens = @token_get_all($code);
    $symbols = [];
    foreach ($tokens as $token) {
        if (! is_array($token)) {
            continue;
        }

        if (! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
            continue;
        }
        $text = ltrim($token[1], '\\');

        if (! str_starts_with($text, 'CoreX\\')) {
            continue;
        }
        $symbols[$text] = true;
    }

    if (preg_match_all('/\\\\CoreX\\\\[A-Za-z0-9\\\\]+/', $code, $matches) !== false) {
        foreach ($matches[0] as $match) {
            $symbols[ltrim($match, '\\')] = true;
        }
    }
    $names = array_keys($symbols);
    sort($names);

    return $names;
}

/** @param  list<string>  $publicApi */
function coveredByPublicApi(string $fqcn, array $publicApi): bool
{
    return in_array($fqcn, $publicApi, true);
}

/** @return array<string, list<string>> */
function cohortPublicApiMaps(string $repoRoot): array
{
    static $surface = null;

    if (! $surface instanceof SurfaceReport) {
        $surface = (new SurfaceScanner)->scan($repoRoot);
    }
    $maps = [];
    foreach ($surface->manifests as $name => $manifest) {
        $maps[$name] = array_keys($manifest['symbols']);
    }

    return $maps;
}

/** @param  array<string, list<string>>  $maps */
function coveredByAnyPublicApi(string $fqcn, array $maps): bool
{
    foreach ($maps as $prefixes) {
        if (coveredByPublicApi($fqcn, $prefixes)) {
            return true;
        }
    }

    return false;
}

/**
 * @param  array{name: string, path: string, dir: string, composer: array<string, mixed>}  $package
 * @param  list<string>  $symbols
 * @return array{archive: string, missing: list<string>}
 */
function compileAgainstArchive(string $repoRoot, array $package, array $symbols): array
{
    $cohortPath = $repoRoot.'/scripts/package-docs-cohort.json';
    $cohort = json_decode((string) file_get_contents($cohortPath), true);
    $archiveSha = null;
    foreach ($cohort['packages'] ?? [] as $row) {
        if (($row['name'] ?? '') === $package['name']) {
            $archiveSha = $row['archive_sha256'] ?? null;

            break;
        }
    }

    $builder = $repoRoot.'/scripts/distribution/release_candidate.py';
    $output = sys_get_temp_dir().'/corex-docs-candidate-'.getmypid();

    if (! is_dir($output.'/artifacts')) {
        $cmd = 'python3 '.escapeshellarg($builder).' build --version 1.0.0 --output '.escapeshellarg($output);
        $lines = [];
        $code = 0;
        exec($cmd.' 2>&1', $lines, $code);

        if ($code !== 0) {
            throw new RuntimeException('candidate build failed: '.implode("\n", $lines));
        }
    }

    $zips = glob($output.'/artifacts/*.zip') ?: [];
    $zipPath = null;
    foreach ($zips as $zip) {
        if ($archiveSha !== null && hash_file('sha256', $zip) === $archiveSha) {
            $zipPath = $zip;

            break;
        }

        if (str_contains($zip, str_replace('/', '-', $package['name']))) {
            $zipPath = $zip;
        }
    }

    if ($zipPath === null) {
        return ['archive' => '', 'missing' => $symbols];
    }

    $extract = $output.'/extract-'.$package['dir'];

    if (! is_dir($extract)) {
        mkdir($extract, 0755, true);
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return ['archive' => $zipPath, 'missing' => $symbols];
        }
        $zip->extractTo($extract);
        $zip->close();
    }

    $missing = [];
    foreach ($symbols as $symbol) {
        if (! classFileExists($extract, $symbol)) {
            $missing[] = $symbol;
        }
    }

    return ['archive' => $zipPath, 'missing' => $missing];
}

function classFileExists(string $extractRoot, string $fqcn): bool
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractRoot));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getFilename() !== 'composer.json') {
            continue;
        }
        $composer = json_decode((string) file_get_contents($file->getPathname()), true);

        if (! is_array($composer)) {
            continue;
        }
        $base = dirname($file->getPathname());
        foreach ($composer['autoload']['psr-4'] ?? [] as $ns => $dir) {
            $ns = (string) $ns;

            if (! str_starts_with($fqcn.'\\', $ns) && $fqcn !== rtrim($ns, '\\')) {
                continue;
            }
            $relative = substr($fqcn, strlen(rtrim($ns, '\\')));
            $relative = ltrim(str_replace('\\', '/', $relative), '/').'.php';
            foreach ((array) $dir as $directory) {
                $candidate = rtrim($base.'/'.trim((string) $directory, '/'), '/').'/'.$relative;

                if (is_file($candidate)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * @param  array<string, mixed>  $report
 */
function formatReport(array $report): string
{
    if ($report['ok'] === true) {
        return 'package-docs-gate: OK cohort='.$report['cohort_count']."\n";
    }
    $lines = ['package-docs-gate: FAIL'];
    foreach ($report['violations'] as $violation) {
        $lines[] = sprintf(
            '%s %s heading=%s symbol=%s producer=%s %s',
            $violation['package'],
            $violation['path'],
            $violation['heading'],
            $violation['symbol'] !== '' ? $violation['symbol'] : '-',
            $violation['producer_phase'],
            $violation['detail'],
        );
    }

    return implode("\n", $lines)."\n";
}
