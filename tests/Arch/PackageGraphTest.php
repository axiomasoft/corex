<?php

declare(strict_types=1);

// P12.3 / P12A.4 / P3.3 — executable package graph gate (R1–R5, D41).

// Gate runs (incl. tampered probes) must never rewrite or delete the tracked report artifact.
function packageGraphTestReport(): string
{
    return sys_get_temp_dir().'/corex-package-graph-test-report.json';
}

beforeEach(fn () => putenv('PACKAGE_GRAPH_REPORT='.packageGraphTestReport()));
afterEach(fn () => putenv('PACKAGE_GRAPH_REPORT'));

test('package graph gate reports no violations', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($output)->not->toBeEmpty();

    $joined = implode("\n", $output);

    expect($joined)->toContain('package-graph-gate: OK')
        ->and($joined)->not->toContain('LEGACY NAMESPACE SPLIT:')
        ->and($joined)->toContain('HOST-RESERVED NAMESPACE: CoreX\\SearchMeilisearch\\')
        ->and($joined)->not->toContain('UNDECLARED DEPENDENCY EXCEPTION: axiomasoft/corex-tasks-workflow -> axiomasoft/corex-wakeup');

    $reportPath = packageGraphTestReport();
    expect(is_file($reportPath))->toBeTrue();

    $report = json_decode((string) file_get_contents($reportPath), true, flags: JSON_THROW_ON_ERROR);

    expect($report['violations'] ?? null)->toBe([])
        ->and($report['legacy_namespace_splits'] ?? null)->toBe([])
        ->and($report['reservations'] ?? null)->not->toBeEmpty()
        ->and($report['undeclared_dependency_exceptions'] ?? null)->toBe([]);
});

test('package graph gate forbids a notifications import of corex-wakeup', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/notifications/src/Digest/Internal/DigestTick.php';
    $originalContents = (string) file_get_contents($probePath);
    $tamperedContents = str_replace(
        'namespace CoreX\\Notifications\\Digest\\Internal;',
        "namespace CoreX\\Notifications\\Digest\\Internal;\n\nuse CoreX\\Wakeup\\Contracts\\DueWakeupSweeper;",
        $originalContents,
    );

    file_put_contents($probePath, $tamperedContents);

    try {
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1', $output, $exitCode);
    } finally {
        file_put_contents($probePath, $originalContents);
    }

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('axiomasoft/corex-notifications → axiomasoft/corex-wakeup')
        ->and($joined)->toContain('DigestTick.php');
});

test('package graph gate reports R3 when host defines classes under reserved prefix', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/search/src/Internal/ProbeMeilisearchViolation.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch;

final class ProbeMeilisearchViolation
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $output = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1', $output, $exitCode);
    } finally {
        unlink($probePath);
    }

    $joined = implode("\n", $output);

    expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and($joined)->toContain('[R3]')
        ->and($joined)->toContain('host defines classes under reserved prefix');
});

test('package graph gate reports R1 for group-use import (tokenizer graph)', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/GroupUseProbe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use CoreX\Wakeup\Contracts\{WakeupScheduleHint};

final class GroupUseProbe
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toMatch('/\[(R1|R2)\]/')
            ->and($joined)->toContain('GroupUseProbe.php');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R1 for FQCN reference without use (tokenizer graph)', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/FqcnGraphProbe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class FqcnGraphProbe
{
    public function touch(): void
    {
        new \CoreX\Wakeup\Contracts\WakeupScheduleHint();
    }
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toMatch('/\[(R1|R2)\]/')
            ->and($joined)->toContain('FqcnGraphProbe.php');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R4 for unknown symbol outside source membership', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/PublicApiR4Probe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use CoreX\Tenancy\Support\DoesNotExist;

final class PublicApiR4Probe
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('[R4]')
            ->and($joined)->toContain('PublicApiR4Probe.php')
            ->and($joined)->toContain('непубличного');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R2 for foreign Internal without R4 wording', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/InternalR2Probe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use CoreX\Tenancy\Internal\X;

final class InternalR2Probe
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('foreign Internal')
            ->and($joined)->toContain('InternalR2Probe.php')
            ->and($joined)->not->toContain('непубличного');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R4b for accidental public top-level type', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/AccidentalPublic.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class AccidentalPublic
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('[R4b]')
            ->and($joined)->toContain('AccidentalPublic.php');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R5 for foundation importing product namespace', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $probePath = $repoRoot.'/packages/core/src/Config/PackageTypeR5Probe.php';
    $probeContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

use Flex\Crm\Contracts\X;

final class PackageTypeR5Probe
{
}
PHP;

    file_put_contents($probePath, $probeContents);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('[R5]')
            ->and($joined)->toContain('foundation')
            ->and($joined)->toContain('product')
            ->and($joined)->toContain('PackageTypeR5Probe.php');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('package graph gate reports R1 for wakeup import outside Integration/Wakeup', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $fixture = $repoRoot.'/tests/fixtures/package-graph/tenancy-wakeup-foreign-context.php';
    $probePath = $repoRoot.'/packages/tenancy/src/Account/WakeupForeignContextProbe.php';

    expect(is_file($fixture))->toBeTrue();
    copy($fixture, $probePath);

    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' 2>&1';
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $joined = implode("\n", $output);

        expect($exitCode)->toBe(1, "gate exit {$exitCode}:\n".implode("\n", $output))
            ->and($joined)->toContain('[R1]')
            ->and($joined)->toContain('WakeupForeignContextProbe.php')
            ->and($joined)->toContain('axiomasoft/corex-wakeup');
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/package-graph-gate.php').' >/dev/null 2>&1');
    }
});

test('extension contract gate classifies registry-like surfaces and rejects a new authority candidate', function (): void {
    $root = dirname(__DIR__, 2);
    $gate = escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/extension-contract-gate.php');
    $output = [];
    $status = 0;

    exec($gate.' 2>&1', $output, $status);
    expect($status)->toBe(0, "gate exit {$status}:\n".implode("\n", $output))
        ->and(implode("\n", $output))->toContain('extension-contract-gate: OK');

    $output = [];
    exec($gate.' --fixture '.escapeshellarg($root.'/tests/fixtures/extension-contract/unclassified.php').' 2>&1', $output, $status);
    expect($status)->toBe(1, "gate exit {$status}:\n".implode("\n", $output))
        ->and(implode("\n", $output))->toContain('unclassified CoreX\\Fixture\\SurpriseRegistry');
});

test('package graph rejects an invented generated symbol without authorizing its import', function (): void {
    $root = dirname(__DIR__, 2);
    $manifest = $root.'/packages/tenancy/resources/contracts/php-api.json';
    $original = file_get_contents($manifest);
    $probe = $root.'/packages/core/src/Config/StaleMembershipProbe.php';

    try {
        $snapshot = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
        $snapshot['symbols']['CoreX\\Tenancy\\Support\\DoesNotExist'] = ['layer' => 'api'];
        file_put_contents($manifest, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($probe, '<?php namespace CoreX\\Config; use CoreX\\Tenancy\\Support\\DoesNotExist; final class StaleMembershipProbe {}');
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/scripts/package-graph-gate.php').' 2>&1', $output, $status);
        expect($status)->toBe(1, "gate exit {$status}:\n".implode("\n", $output))
            ->and(implode("\n", $output))->toContain('[R4]', '[R4b]', 'API-STALE', 'DoesNotExist');
    } finally {
        file_put_contents($manifest, $original);
        @unlink($probe);
    }
});
