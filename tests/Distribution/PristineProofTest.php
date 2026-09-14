<?php

declare(strict_types=1);

it('installs a sealed candidate into a consumer outside the builder checkout', function (): void {
    $command = ['python3', dirname(__DIR__, 2).'/scripts/distribution/pristine-proof.py', '--package', 'corex/core', '--laravel', '12'];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('Не удалось запустить pristine-proof.py.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $stderr);

    $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);

    expect($result['cells'])->toHaveCount(1)
        ->and($result['cells'][0]['package'])->toBe('corex/core')
        ->and($result['cells'][0]['fresh_install'])->toBe('passed')
        ->and($result['cells'][0]['installed_source'])->toContain('vendor/corex/core');
});
