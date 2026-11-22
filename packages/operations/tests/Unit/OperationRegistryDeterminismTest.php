<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Testing\StubOperationHandler;
use CoreX\Operations\Tests\TestCase;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Exceptions\Compiler\CompilationException;

uses(TestCase::class);

it('rejects duplicate normalized route identity and names both owners', function (): void {
    $operation = static fn (): Api => Api::operation(
        id: 'notes.show',
        method: 'GET',
        path: '/notes/{id}',
        handler: StubOperationHandler::class,
        ability: 'notes.view',
        requestSchema: ['type' => 'object', 'additionalProperties' => false],
        responseSchema: ['type' => 'object', 'additionalProperties' => false],
        version: 'v2',
    );

    $alpha = ModuleManifest::make('example/alpha')->title('A')->tablePrefix('aaa')->extends([$operation()]);
    $beta = ModuleManifest::make('example/beta')->title('B')->tablePrefix('bbb')->extends([$operation()]);

    expect(fn () => resolve(ModuleCompilation::class)->compile([$alpha, $beta], []))
        ->toThrow(CompilationException::class, 'Duplicate identity [api.operation:v2:notes.show]');
});
