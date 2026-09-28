<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Unit;

use CoreX\Api\Tests\Support\StubOperationHandler;
use CoreX\Api\Tests\TestCase;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;

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
    );

    $alpha = ModuleManifest::make('example/alpha')->title('A')->tablePrefix('aaa')->extends([$operation()]);
    $beta = ModuleManifest::make('example/beta')->title('B')->tablePrefix('bbb')->extends([$operation()]);

    expect(fn () => app(RegistryCompiler::class)->compile([$alpha, $beta], []))
        ->toThrow(CompilationException::class, 'Duplicate identity [api.operation:v1:notes.show]');
});
