<?php

declare(strict_types=1);

// P05.3 — blocking runtime-globals gate (D70 п.5–9). Each rule has a planted negative control
// that must exit 1 and name the probe file; the baseline is a ratchet limited to Impersonation.

/**
 * @return array{0: int, 1: string}
 */
function runtimeGlobalsGate(string ...$arguments): array
{
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/runtime-globals-gate.php');

    foreach ($arguments as $argument) {
        $command .= ' '.escapeshellarg($argument);
    }

    $output = [];
    $exitCode = 0;

    exec($command.' 2>&1', $output, $exitCode);

    return [$exitCode, implode("\n", $output)];
}

/**
 * @return array{0: int, 1: string}
 */
function runtimeGlobalsGateWithProbe(string $body): array
{
    $probePath = dirname(__DIR__, 2).'/packages/core/src/Config/RuntimeGlobalsProbe.php';
    $contents = <<<PHP
<?php

declare(strict_types=1);

namespace CoreX\Config;

{$body}
PHP;

    file_put_contents($probePath, $contents);

    try {
        return runtimeGlobalsGate();
    } finally {
        if (is_file($probePath)) {
            unlink($probePath);
        }
    }
}

test('runtime globals gate exits 0 on a clean tree', function (): void {
    [$exitCode, $output] = runtimeGlobalsGate();

    expect($exitCode)->toBe(0, "gate exit {$exitCode}:\n".$output)
        ->and($output)->toContain('G1 0, G2 0, G3 0, G4 0, G5 0 outside baseline')
        ->and($output)->toContain('runtime-globals-gate: OK');
});

test('runtime globals baseline only holds the Impersonation zone', function (): void {
    $baseline = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/scripts/runtime-globals-baseline.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($baseline)->not->toBeEmpty();

    foreach (array_keys($baseline) as $path) {
        expect($path)->toStartWith('packages/tenancy/src/Impersonation/');
    }
});

test('runtime globals gate G1 names a planted facade import', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use Illuminate\Support\Facades\Cache;

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return Cache::get('probe');
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G1 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G2 names a planted helper call', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return app('probe');
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G2 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G3 names a planted static clock', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return \Carbon\CarbonImmutable::now();
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G3 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G4 names a planted static property', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
final class RuntimeGlobalsProbe
{
    private static int $counter = 0;

    public function bump(): int
    {
        return ++self::$counter;
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G4 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G5 names a planted anonymous class', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
final class RuntimeGlobalsProbe
{
    public function make(): object
    {
        return new class {};
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G5 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate exits 1 with baseline exceeded when a baselined file gains a finding', function (): void {
    $baselinePath = sys_get_temp_dir().'/runtime-globals-baseline-'.bin2hex(random_bytes(4)).'.json';
    $baseline = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/scripts/runtime-globals-baseline.json'), true, flags: JSON_THROW_ON_ERROR);
    $file = 'packages/tenancy/src/Impersonation/Internal/DatabaseImpersonationService.php';
    $baseline[$file]['G2']--;
    file_put_contents($baselinePath, json_encode($baseline, JSON_THROW_ON_ERROR));

    try {
        [$exitCode, $output] = runtimeGlobalsGate('--baseline='.$baselinePath);
    } finally {
        unlink($baselinePath);
    }

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('baseline exceeded');
});

test('runtime globals gate exits 2 when the baseline names a path outside the Impersonation prefix', function (): void {
    $baselinePath = sys_get_temp_dir().'/runtime-globals-baseline-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($baselinePath, json_encode(['packages/core/src/Config/Options.php' => ['G2' => 1]], JSON_THROW_ON_ERROR));

    try {
        [$exitCode, $output] = runtimeGlobalsGate('--baseline='.$baselinePath);
    } finally {
        unlink($baselinePath);
    }

    expect($exitCode)->toBe(2, $output)
        ->and($output)->toContain('baseline path outside baseline_prefixes');
});

test('runtime globals gate G1 sees a facade imported through group syntax', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use Illuminate\Support\{Facades\DB};

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return DB::table('probe');
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G1 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G2 sees a helper imported under an alias', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use function now as clockNow;

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return clockNow();
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G2 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G3 sees a Carbon class imported under an alias', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use Carbon\CarbonImmutable as Moment;

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return Moment::now();
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G3 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G4 sees an intersection-typed static property', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
final class RuntimeGlobalsProbe
{
    private static \Countable&\Iterator $state;
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G4 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G1 sees a facade reached through a namespace alias', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use Illuminate\Support as Support;

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return Support\Facades\DB::table('probe');
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G1 packages/core/src/Config/RuntimeGlobalsProbe.php');
});

test('runtime globals gate G3 matches an aliased Carbon class case-insensitively', function (): void {
    [$exitCode, $output] = runtimeGlobalsGateWithProbe(<<<'PHP'
use Carbon\CarbonImmutable as Moment;

final class RuntimeGlobalsProbe
{
    public function read(): mixed
    {
        return MOMENT::now();
    }
}
PHP);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('G3 packages/core/src/Config/RuntimeGlobalsProbe.php');
});
