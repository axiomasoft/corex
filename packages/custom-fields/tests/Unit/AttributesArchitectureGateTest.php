<?php

declare(strict_types=1);
use Symfony\Component\Process\Process;

/** @return array{int, string} */
function runAttributesArchitectureGate(?string $fixture = null): array
{
    $root = dirname(__DIR__, 4);
    $command = [PHP_BINARY, $root.'/scripts/attributes-architecture-gate.php'];

    if ($fixture !== null) {
        $command = [...$command, '--fixture', $root.'/tests/fixtures/attributes-architecture/'.$fixture];
    }

    $process = new Process($command, $root);
    $process->run();

    return [$process->getExitCode() ?? 2, $process->getOutput().$process->getErrorOutput()];
}

it('passes the package architecture ratchet on the current source tree', function (): void {
    [$status, $output] = runAttributesArchitectureGate();

    expect($status)->toBe(0, "attributes-architecture-gate exit {$status}:\n".$output)->and($output)->toContain('attributes-architecture-gate: OK');
});

it('rejects each planted architecture violation for its own reason', function (string $fixture, string $reason): void {
    [$status, $output] = runAttributesArchitectureGate($fixture);

    expect($status)->toBe(1, "attributes-architecture-gate exit {$status}:\n".$output)->and($output)->toContain($reason);
})->with([
    ['search-import.php.fixture', 'A1 search import'],
    ['runtime-config.php.fixture', 'A2 runtime config()'],
    ['foreign-ddl.php.fixture', 'A3 foreign DDL users'],
    ['dynamic-ddl.php.fixture', 'A3 dynamic DDL target'],
    ['retired-eav.php.fixture', 'A3 foreign DDL sys_attribute_eav_values'],
    ['per-field-driver.json', 'A4'],
    ['non-boolean-ddl.json', 'A4'],
    ['concurrent-index.php.fixture', 'A6 concurrent index DDL outside the owner executor'],
]);
