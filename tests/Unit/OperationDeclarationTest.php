<?php

declare(strict_types=1);

use CoreX\CoreServiceProvider;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\OperationsServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Packman\Modules\Contracts\Compiler\ManifestSectionCompiler;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Exceptions\Compiler\CompilationException;

final class CoreXDeclarationFixture implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}

it('compiles operation declarations with a single compiler in each installation profile', function (bool $runtime): void {
    $providers = $runtime ? [CoreServiceProvider::class, OperationsServiceProvider::class] : [CoreServiceProvider::class];
    $app = Application::create(options: ['extra' => ['providers' => $providers, 'dont-discover' => ['*']]]);
    $sections = iterator_to_array($app->tagged(ManifestSectionCompiler::class));

    expect(array_filter($sections, static fn (ManifestSectionCompiler $compiler): bool => $compiler->mode() === 'operation'))->toHaveCount(1);
    $compiler = $app->make(ModuleCompilation::class);
    $operation = Api::operation('records.list', 'GET', '/records', CoreXDeclarationFixture::class, 'records.read', [], []);
    $manifest = ModuleManifest::make('acme/records')->title('Records')->extends([$operation]);
    expect($compiler->compile([$manifest])->api[0]->operationId)->toBe('records.list');

    $invalid = clone $operation;
    $invalid->path = '/{id}/{id}';
    $invalidManifest = ModuleManifest::make('acme/records')->title('Records')->extends([$invalid]);
    expect(fn () => $compiler->compile([$invalidManifest]))->toThrow(CompilationException::class, 'Repeated API path parameter');
})->with(['kernel only' => false, 'with operations' => true]);
