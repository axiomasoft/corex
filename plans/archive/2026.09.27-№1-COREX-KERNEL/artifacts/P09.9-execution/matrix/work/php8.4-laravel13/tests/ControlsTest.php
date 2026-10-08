<?php
declare(strict_types=1);
use CoreX\InfectionSpike\Controls;

it('executes the escaped control without asserting its boolean', function (): void {
    Controls::escaped();
    expect(true)->toBeTrue();
});
it('executes the timeout control', function (): void {
    if (Controls::timeout()) {
        pcntl_exec(PHP_BINARY, ['-r', 'fwrite(STDOUT, "SPIKE_TIMEOUT_CONTROL\\n"); sleep(10);']);
        throw new RuntimeException('Could not execute the timeout control.');
    }
    expect(true)->toBeTrue();
});
it('executes the process error control', function (): void {
    if (Controls::error()) {
        pcntl_exec(PHP_BINARY, ['-r', 'fwrite(STDOUT, "SPIKE_ERROR_CONTROL\\n"); exit(137);']);
        throw new RuntimeException('Could not execute the error control.');
    }
    expect(true)->toBeTrue();
});
