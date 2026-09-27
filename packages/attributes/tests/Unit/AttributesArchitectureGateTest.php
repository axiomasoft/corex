<?php

declare(strict_types=1);

/** @return array{int, string} */
function runAttributesArchitectureGate(?string $fixture = null): array
{
    $root = dirname(__DIR__, 4);
    $command = [PHP_BINARY, $root.'/scripts/attributes-architecture-gate.php'];

    if ($fixture !== null) {
        $command = [...$command, '--fixture', $root.'/tests/fixtures/attributes-architecture/'.$fixture];
    }

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start attributes architecture gate.');
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

it('passes the package architecture ratchet on the current source tree', function (): void {
    [$status, $output] = runAttributesArchitectureGate();

    expect($status)->toBe(0)->and($output)->toContain('attributes-architecture-gate: OK');
});

it('rejects each planted architecture violation for its own reason', function (string $fixture, string $reason): void {
    [$status, $output] = runAttributesArchitectureGate($fixture);

    expect($status)->toBe(1)->and($output)->toContain($reason);
})->with([
    ['search-import.php.fixture', 'A1 search import'],
    ['runtime-config.php.fixture', 'A2 runtime config()'],
    ['foreign-ddl.php.fixture', 'A3 foreign DDL users'],
    ['per-field-driver.json', 'A4'],
]);
