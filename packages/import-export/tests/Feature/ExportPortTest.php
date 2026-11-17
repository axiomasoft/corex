<?php

declare(strict_types=1);

use CoreX\ImportExport\Contracts\ExportRunner;
use CoreX\ImportExport\Internal\Export\DatabaseExportRunner;

it('publishes the existing seal and emit signatures on ExportRunner', function (): void {
    $port = new ReflectionClass(ExportRunner::class);
    $runner = new ReflectionClass(DatabaseExportRunner::class);
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($runner->implementsInterface(ExportRunner::class))->toBeTrue()
        ->and($port->getMethod('seal')->getParameters()[0]->getName())->toBe('scope')
        ->and($port->getMethod('emit')->getNumberOfParameters())->toBe($runner->getMethod('emit')->getNumberOfParameters())
        ->and($port->getMethod('seal')->getNumberOfParameters())->toBe($runner->getMethod('seal')->getNumberOfParameters())
        ->and(array_keys($composer['require']))->not->toContain('axiomasoft/corex-operations');
});
