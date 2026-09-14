<?php

declare(strict_types=1);

it('installs the immutable private auth dependency in both consumer roles', function (): void {
    $command = ['python3', dirname(__DIR__, 2).'/scripts/distribution/private-auth-proof.py'];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('Не удалось запустить private-auth-proof.py.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $stderr);

    $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);

    expect($result['ref'])->toBe('8c83864f02017134b325224c6c8be590a3971485')
        ->and($result['auth_root_lock_sha256'])->toHaveLength(64)
        ->and($result['auth_dependency_lock_sha256'])->toHaveLength(64);
});
