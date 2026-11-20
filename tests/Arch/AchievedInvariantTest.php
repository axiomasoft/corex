<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

const SEARCH_BACKEND_IMPLEMENTATION = '/\bimplements\s+[^{};]*\b(?:CandidateDriver|LiveCandidateDriver|ExactCandidateDriver|IndexDriver)\b/';

// P3.7 — achieved audit invariants A57, A61, A99 and table-ownership gate T1/T2/T4 (D19).

test('attributes package does not depend on search or meilisearch (A57)', function (): void {
    $offenders = [
        ...SourceScanner::filesMatching(SourceScanner::packagePath('custom-fields', 'src'), '/\buse\s+CoreX\\\\Search\\\\/'),
        ...SourceScanner::filesMatching(SourceScanner::packagePath('custom-fields', 'src'), '/\bMeilisearch\b/'),
    ];

    expect($offenders)->toBe([]);
});

test('attributes search import probe fails A57 with file path', function (): void {
    $probePath = SourceScanner::packagePath('custom-fields', 'src/Internal/A57Probe.php');
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use CoreX\Search\Query\Contracts\Search;

final class A57Probe
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    $offenders = SourceScanner::filesMatching(SourceScanner::packagePath('custom-fields', 'src'), '/\buse\s+CoreX\\\\Search\\\\/');

    unlink($probePath);

    expect($offenders)->toHaveCount(1)
        ->and($offenders[0])->toContain('A57Probe.php');
});

test('search backend adapters do not import eloquent (A61)', function (): void {
    foreach (['search', 'search-meilisearch'] as $package) {
        $drivers = SourceScanner::filesMatching(SourceScanner::packagePath($package, 'src'), SEARCH_BACKEND_IMPLEMENTATION);

        expect($drivers)->not->toBeEmpty("no backend adapter detected in {$package}");

        foreach ($drivers as $file) {
            expect((string) file_get_contents($file))->not->toMatch('/\buse\s+Illuminate\\\\Database\\\\Eloquent\b/', $file);
        }
    }
});

test('search contracts are split across dedicated files (A61)', function (): void {
    $expected = [
        'Query/Contracts/Search.php',
        'Query/Contracts/CandidateDriver.php',
        'Projection/Contracts/SearchSource.php',
        'Projection/Contracts/SearchProjectionContributor.php',
        'Index/Contracts/IndexDriver.php',
        'Rebuild/Contracts/SearchRebuilds.php',
    ];

    foreach ($expected as $file) {
        expect(is_file(SourceScanner::packagePath('search', 'src/'.$file)))->toBeTrue("missing contract file {$file}");
    }
});

test('search backend eloquent import probe fails A61 with file path', function (): void {
    $probePath = SourceScanner::packagePath('search', 'src/A61ProbeCandidateDriver.php');
    $probeContents = <<<'PHP'
    <?php
    
    declare(strict_types=1);
    
    namespace CoreX\Search\Query\Internal;
    
    use CoreX\Search\Query\Contracts\CandidateDriver;
    use Illuminate\Database\Eloquent\Model;
    
    abstract class A61ProbeCandidateDriver implements CandidateDriver
    {
    }
    PHP;

    file_put_contents($probePath, $probeContents);

    $offenders = SourceScanner::filesMatching(SourceScanner::packagePath('search', 'src'), SEARCH_BACKEND_IMPLEMENTATION);
    $offenders = array_values(array_filter(
        $offenders,
        static fn (string $file): bool => preg_match('/\buse\s+Illuminate\\\\Database\\\\Eloquent\b/', (string) file_get_contents($file)) === 1,
    ));

    unlink($probePath);

    expect($offenders)->toHaveCount(1)
        ->and($offenders[0])->toContain('A61ProbeCandidateDriver.php');
});

test('trusted context resolver does not read tenant id from the request payload (A99)', function (): void {
    $path = SourceScanner::packagePath('operations', 'src/Invocation/Internal/TrustedContextResolver.php');
    $src = (string) file_get_contents($path);

    expect($src)->not->toMatch('/\$request->input/')
        ->and($src)->not->toMatch('/\$request->get\(/')
        ->and($src)->not->toMatch('/\$request->query\(/')
        ->and($src)->not->toMatch('/tenant_id/')
        ->and($src)->not->toMatch('/account_id/')
        ->and($src)->toContain('$this->tenants->current()');
});

test('trusted context resolver tenant_id probe fails A99 with file path', function (): void {
    $path = SourceScanner::packagePath('operations', 'src/Invocation/Internal/TrustedContextResolver.php');
    $original = (string) file_get_contents($path);
    $tampered = str_replace(
        '$tenant = $this->tenants->current();',
        "\$tenant = \$this->tenants->current();\n        \$request->input('tenant_id');",
        $original,
    );

    file_put_contents($path, $tampered);

    $src = (string) file_get_contents($path);

    file_put_contents($path, $original);

    expect($src)->toMatch('/\$request->input\(/')
        ->and($src)->toContain('tenant_id');
});

test('table ownership gate reports no violations on clean tree', function (): void {
    $repoRoot = SourceScanner::repoRoot();
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/table-ownership-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('table-ownership-gate: OK');
});

test('table ownership gate delegates registered uncatalogued creates to S9', function (): void {
    $repoRoot = SourceScanner::repoRoot();
    $probePath = $repoRoot.'/packages/core/database/migrations/tenant/2099_01_01_000000_table_ownership_t1_probe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zzz_probe_table_ownership', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zzz_probe_table_ownership');
    }
};
PHP;

    file_put_contents($probePath, $probeContents);

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/table-ownership-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    unlink($probePath);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('S9')
        ->and($joined)->toContain('2099_01_01_000000_table_ownership_t1_probe.php');
});

test('table ownership gate reports T4 for core cross-package foreign key probe', function (): void {
    $repoRoot = SourceScanner::repoRoot();
    $probePath = $repoRoot.'/packages/core/database/migrations/tenant/2099_01_01_000001_table_ownership_t4_probe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_settings', function (Blueprint $table): void {
            $table->foreign('id')->references('id')->on('wsp_workspaces');
        });
    }

    public function down(): void
    {
        Schema::table('sys_settings', function (Blueprint $table): void {
            $table->dropForeign(['id']);
        });
    }
};
PHP;

    file_put_contents($probePath, $probeContents);

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/table-ownership-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    unlink($probePath);

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('T4')
        ->and($joined)->toContain('2099_01_01_000001_table_ownership_t4_probe.php');
});
