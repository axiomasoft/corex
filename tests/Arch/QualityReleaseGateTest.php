<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('rejects suppressed debt, incomplete analysis scopes and nonzero completed ceilings', function (): void {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/corex-quality-'.bin2hex(random_bytes(8));
    $files = new Filesystem;

    try {
        foreach (['scripts/quality', 'tests/Support', 'tests/Golden', 'tests/fixtures/extension-contract', 'packages/core/src', 'packages/core/database/migrations/tenant', 'packages/access-azguard/src', 'packages/access-azguard/database/migrations/tenant'] as $dir) {
            $files->makeDirectory($fixture.'/'.$dir, 0755, true);
        }
        $files->put($fixture.'/tests/fixtures/extension-contract/fixture-adapter.php', '<?php');
        $files->copy($root.'/phpstan-extensions.neon', $fixture.'/phpstan-extensions.neon');
        foreach (['.', 'packages/core', 'packages/access-azguard'] as $dir) {
            $config = file_get_contents($root.'/'.$dir.'/phpstan.neon');
            $files->put($fixture.'/'.$dir.'/phpstan.neon', $config);

            if (str_contains($config, '    - phpstan-baseline.neon')) {
                $files->put($fixture.'/'.$dir.'/phpstan-baseline.neon', "parameters:\n    ignoreErrors: []\n");
            }
        }
        $files->put($fixture.'/scripts/quality-ceilings.json', json_encode(['phpstan' => ['root' => 0, 'core' => 0, 'access-azguard' => 0, 'access' => 30]], JSON_THROW_ON_ERROR));
        $files->put($fixture.'/scripts/schema-sets.json', json_encode(['sets' => [['path' => 'packages/core/database/migrations/tenant'], ['path' => 'packages/access-azguard/database/migrations/tenant']]], JSON_THROW_ON_ERROR));
        // The package config includes this additional existing support path.
        $files->makeDirectory($fixture.'/packages/core/tests/Support', 0755, true);
        $files->put($fixture.'/packages/core/tests/Support/CoreXTestCase.php', '<?php');
        $run = static function () use ($root, $fixture): Process {
            $process = new Process([PHP_BINARY, $root.'/scripts/quality/release-quality.php', '--stage=foundation', '--root='.$fixture]);
            $process->run();

            return $process;
        };
        expect($run()->getExitCode())->toBe(0);
        $files->put($fixture.'/packages/core/src/ignored.php', "<?php\n// @phpstan-ignore-next-line\n");
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('ignored.php:2: inline suppression');
        $files->delete($fixture.'/packages/core/src/ignored.php');
        $path = $fixture.'/packages/core/phpstan.neon';
        $config = $files->get($path);
        $files->put($path, str_replace('        - database/migrations', '        - tests/Support', $config));
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('missing analysis path database/migrations');
        $files->put($path, $config);
        foreach (["    strictRules:\n        allRules: false\n", "    strictRules:\n        booleansInConditions: [true, false]\n", "    featureToggles:\n        bleedingEdge: false\n"] as $override) {
            $files->put($path, $config.$override);
            expect($run()->getExitCode())->toBe(1)
                ->and($run()->getErrorOutput())->toContain('core: disabled strict/bleedingEdge rules');
        }
        $files->put($path, $config);
        $files->put($fixture.'/packages/core/phpstan-baseline.neon', "parameters:\n    ignoreErrors:\n        - '#ignored#'\n");
        expect($run()->getExitCode())->toBe(1);
        $files->put($fixture.'/packages/core/phpstan-baseline.neon', "parameters:\n    ignoreErrors: []\n");
        $files->put($fixture.'/scripts/quality-ceilings.json', json_encode(['phpstan' => ['root' => 0, 'core' => 1, 'access-azguard' => 0]], JSON_THROW_ON_ERROR));
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('core: ceiling must be zero');
    } finally {
        $files->deleteDirectory($fixture);
    }
});

it('admits schema-free security packages while retaining every source and migration boundary', function (): void {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/corex-security-quality-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $packages = ['core', 'access-azguard', 'access', 'tenancy', 'operations', 'tools', 'webhooks'];

    try {
        foreach (['scripts/quality', 'tests/Support', 'tests/Golden'] as $dir) {
            $files->makeDirectory($fixture.'/'.$dir, 0755, true);
        }
        $files->copy($root.'/phpstan-extensions.neon', $fixture.'/phpstan-extensions.neon');
        $files->put($fixture.'/phpstan-baseline.neon', "parameters:\n    ignoreErrors: []\n");
        $files->put($fixture.'/phpstan.neon', "includes:\n    - phpstan-extensions.neon\n    - phar://phpstan.phar/conf/bleedingEdge.neon\n    - phpstan-baseline.neon\nparameters:\n    level: max\n    paths:\n        - tests/Support\n        - tests/Golden\n        - scripts\n");
        foreach ($packages as $package) {
            $files->makeDirectory($fixture.'/packages/'.$package.'/src', 0755, true);
            $files->put($fixture.'/packages/'.$package.'/phpstan-baseline.neon', "parameters:\n    ignoreErrors: []\n");
            $files->put($fixture.'/packages/'.$package.'/phpstan.neon', "includes:\n    - ../../phpstan-extensions.neon\n    - phar://phpstan.phar/conf/bleedingEdge.neon\n    - phpstan-baseline.neon\nparameters:\n    level: max\n    paths:\n        - src\n");
        }
        $ceilings = ['phpstan' => array_fill_keys(['root', ...$packages], 0)];
        $files->put($fixture.'/scripts/quality-ceilings.json', json_encode($ceilings, JSON_THROW_ON_ERROR));
        $files->put($fixture.'/scripts/schema-sets.json', json_encode(['sets' => []], JSON_THROW_ON_ERROR));
        $run = static function () use ($root, $fixture): Process {
            $process = new Process([PHP_BINARY, $root.'/scripts/quality/release-quality.php', '--stage=security', '--root='.$fixture]);
            $process->run();

            return $process;
        };
        expect($run()->getExitCode())->toBe(0);
        $path = $fixture.'/packages/tools/phpstan.neon';
        $config = $files->get($path);
        $files->put($path, str_replace('        - src', '        - .', $config));
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('tools: missing analysis path src');
        $files->put($path, $config);
        $files->makeDirectory($fixture.'/packages/tools/database/migrations/tenant', 0755, true);
        $files->put($fixture.'/packages/tools/database/migrations/tenant/create.php', '<?php');
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('tools: missing analysis path database/migrations');
        $files->put($fixture.'/scripts/schema-sets.json', json_encode(['sets' => [['path' => 'packages/tools/database/migrations/tenant']]], JSON_THROW_ON_ERROR));
        $files->put($path, $config."        - database/migrations\n");
        expect($run()->getExitCode())->toBe(0);
        $files->deleteDirectory($fixture.'/packages/tools/database/migrations');
        $files->put($path, $config);
        expect($run()->getExitCode())->toBe(1)
            ->and($run()->getErrorOutput())->toContain('tools: uncovered SchemaSet database/migrations/tenant');
    } finally {
        $files->deleteDirectory($fixture);
    }
});
