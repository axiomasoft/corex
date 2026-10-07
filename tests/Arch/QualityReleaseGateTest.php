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
        $run = static function (string $stage = 'security') use ($root, $fixture): Process {
            $process = new Process([PHP_BINARY, $root.'/scripts/quality/release-quality.php', '--stage='.$stage, '--root='.$fixture]);
            $process->run();

            return $process;
        };
        expect($run()->getExitCode())->toBe(0);
        $files->put($fixture.'/packages/tools/src/value.php', '<?php namespace CoreX\\QualityFixture; final class Value {}');
        expect($run('all')->getExitCode())->toBe(0);
        $files->put($fixture.'/packages/tools/src/value.php', '<?php namespace CoreX\\QualityFixture; class Value {}');
        $red = $run('all');
        expect($red->getExitCode())->toBe(1)
            ->and($red->getErrorOutput())->toContain('value.php:1: final: CoreX\\QualityFixture\\Value');
        $files->put($fixture.'/packages/tools/src/value.php', '<?php namespace CoreX\\QualityFixture; final class Value {}');
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

it('enforces final classes, immutable value DTOs and declaration-resolved named calls on temporary production sources', function (): void {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/corex-source-quality-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $source = <<<'SOURCE'
<?php
namespace CoreX\QualityFixture;
use CoreX\Attributes\Spi as Extension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
#[Extension(kind: 'extend')]
abstract class ExtensionBase { public function inherited(string $first, int $second): void {} }
#[Extension(kind: 'extend')]
class ExtensionConcrete extends ExtensionBase {}
final class Record extends Model { public string $mutable = ''; }
final class Provider extends ServiceProvider { public string $mutable = ''; }
function target_function(string $first, int $second): void {}
final class Calls {
    public function __construct(private ExtensionBase $extension) {}
    public static function target(string $first, int $second): self { return new self(new ExtensionConcrete()); }
    public static function variadic(string $first, mixed ...$rest): void {}
    public function named(ExtensionBase $parameter, mixed $unknown): void {
        self::target(first: 'a', second: 2);
        target_function(first: 'a', second: 2);
        new \CoreX\QualityFixture\Data\Pair(first: 'a', second: 2);
        $this->extension->inherited(first: 'a', second: 2);
        $parameter->inherited(first: 'a', second: 2);
        $known = new ExtensionConcrete();
        $known->inherited(first: 'a', second: 2);
        $known = $unknown;
        $known->inherited('unknown', 2);
        $unknown->target('unknown', 2);
        $method = 'target';
        self::$method('dynamic', 2);
        static::target('late-bound', 2);
        self::variadic('variadic', 2);
        self::target(...['unpacked', 2]);
        \External\Calls::target('external', 2);
        $loop = new ExtensionConcrete();
        foreach ([$unknown] as $loop) { $loop->inherited('unknown iteration value', 2); }
        $branch = new ExtensionConcrete();
        if ($unknown) { $branch = $unknown; }
        $branch->inherited('unknown merged branch', 2);
        $shortCircuit = new ExtensionConcrete();
        $unknown && ($shortCircuit = $unknown);
        $shortCircuit->inherited('unknown conditional write', 2);
        $captured = new ExtensionConcrete();
        $callback = function () use (&$captured, $unknown): void { $captured = $unknown; };
        $captured->inherited('unknown reference capture', 2);
    }
}
namespace CoreX\QualityFixture\Data;
final readonly class Pair { public function __construct(public string $first, public int $second) {} }
SOURCE;
    $run = static function () use ($root, $fixture): Process {
        $process = new Process([PHP_BINARY, $root.'/scripts/quality/source-quality.php', '--root='.$fixture, '--json']);
        $process->run();

        return $process;
    };

    try {
        $files->makeDirectory($fixture.'/packages/example/src', 0755, true);
        $path = $fixture.'/packages/example/src/fixture.php';
        $files->put($path, $source);
        $green = $run();
        expect($green->getExitCode())->toBe(0, $green->getErrorOutput());
        $report = json_decode($green->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($report['files'])->toBe(1)
            ->and($report['violations'])->toBe([])
            ->and($report['file_hashes']['packages/example/src/fixture.php'])->toBe(hash('sha256', $source));

        $files->put($path, str_replace('final class Calls', 'class Calls', $source));
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getOutput())->toContain('"rule": "final"', 'CoreX\\\\QualityFixture\\\\Calls');
        $files->put($path, str_replace('final readonly class Pair', 'final class Pair', $source));
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getOutput())->toContain('"rule": "readonly"', 'immutable value DTO');

        foreach (['Record', 'Provider'] as $frameworkClass) {
            $files->put($path, str_replace('final class '.$frameworkClass, 'final readonly class '.$frameworkClass, $source));
            $red = $run();
            expect($red->getExitCode())->toBe(1)
                ->and($red->getOutput())->toContain('"rule": "readonly-framework"');
        }

        foreach ([
            "self::target(first: 'a', second: 2);" => "self::target('a', 2);",
            "target_function(first: 'a', second: 2);" => "target_function('a', 2);",
            "new \\CoreX\\QualityFixture\\Data\\Pair(first: 'a', second: 2);" => "new \\CoreX\\QualityFixture\\Data\\Pair('a', 2);",
            "\$this->extension->inherited(first: 'a', second: 2);" => "\$this->extension->inherited('a', 2);",
            "\$parameter->inherited(first: 'a', second: 2);" => "\$parameter->inherited('a', 2);",
            "\$known->inherited(first: 'a', second: 2);" => "\$known->inherited('a', 2);",
        ] as $named => $positional) {
            $violating = str_replace($named, $positional, $source);
            $files->put($path, $violating);
            $red = $run();
            $report = json_decode($red->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            expect($red->getExitCode())->toBe(1)
                ->and($report['violations'])->toHaveCount(1)
                ->and($report['violations'][0]['rule'])->toBe('named')
                ->and(array_column($report['named_insertions'], 'text'))->toBe(['first: ', 'second: ']);
            // The report's offsets are usable edits against its hashed source, not guessed names.
            foreach (array_reverse($report['named_insertions']) as $insertion) {
                $violating = substr_replace($violating, $insertion['text'], $insertion['offset'], 0);
            }
            $files->put($path, $violating);
            expect($run()->getExitCode())->toBe(0);
        }
        $files->put($path, str_replace('class ExtensionConcrete extends ExtensionBase {}', 'class ExtensionConcrete extends ExtensionBase { public function inherited(string $renamed, int $second): void {} }', $source));
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getOutput())->toContain('"rule": "signature"', 'inconsistent controlled override parameter names');

        $files->put($path, $source."\nfinal class Broken { public function invalid( }");
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getErrorOutput())->toContain('Cannot parse packages/example/src/fixture.php');
        $files->delete($path);
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getErrorOutput())->toContain('No maintained production PHP files');
        $files->deleteDirectory($fixture.'/packages');
        $red = $run();
        expect($red->getExitCode())->toBe(1)
            ->and($red->getErrorOutput())->toContain('No maintained production source roots');
    } finally {
        $files->deleteDirectory($fixture);
    }
});
