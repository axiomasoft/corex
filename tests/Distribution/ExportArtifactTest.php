<?php

declare(strict_types=1);

function runExporter(string ...$arguments): array
{
    $command = ['python3', dirname(__DIR__, 2).'/scripts/distribution/export.py', ...$arguments];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('Не удалось запустить локальный export.py.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

it('builds identical four-package candidates from the pinned Git source', function (): void {
    [$status, $stdout, $stderr] = runExporter('--check');

    expect($status)->toBe(0, $stderr);

    $manifest = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['source_ref'])->toBe('ea7d0dbdf36b122904abba91e124018c11b939ed')
        ->and(array_keys($manifest['packages']))->toBe([
            'axiomasoft/corex',
            'axiomasoft/corex',
            'axiomasoft/corex-tasks',
            'axiomasoft/corex-tenancy',
        ]);
});

it('rejects an unapproved source reference before creating a staging tree', function (): void {
    $staging = sys_get_temp_dir().'/corex-export-denied-'.bin2hex(random_bytes(8));

    [$status, $stdout, $stderr] = runExporter('--source-sha', 'deadbeef', '--staging', $staging);

    expect($status)->toBe(1)
        ->and($stdout)->toBe('')
        ->and($stderr)->toContain('source SHA is not the approved committed candidate')
        ->and(is_dir($staging))->toBeFalse();
});
