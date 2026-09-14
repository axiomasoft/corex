<?php

declare(strict_types=1);

use CoreX\Api\ApiController;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\ErrorBoundary;
use CoreX\Api\OpenApi;
use CoreX\Api\Operation;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use CoreX\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

require __DIR__.'/vendor/autoload.php';

final class ProbePrincipal implements Principal, PrincipalResolver
{
    public function id(): string
    {
        return 'installed-service';
    }

    public function resolve(Request $request): ?Principal
    {
        return $this;
    }
}

final class ProbePolicy implements OperationAuthorizer
{
    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $operation->ability === 'notes.echo' && $context->principal()->id() === 'installed-service';
    }
}

final class ProbeActivation implements ModuleStateReader
{
    public bool $enabled = true;

    public int $epoch = 0;

    public function enabledFor(TenantContext $ctx): array
    {
        return $this->enabled ? ['example/notes' => ['state' => 'enabled', 'edition' => 'standard']] : [];
    }
}

$app = Application::configure(basePath: __DIR__)
    ->withProviders([ApiServiceProvider::class])
    ->withRouting(using: static function (): void {
        Route::post('/chosen/{locale}/{version}/{path}', ApiController::class)
            ->where('path', '.*')->middleware(ErrorBoundary::class);
    })->create();
$app->make(ConsoleKernel::class)->bootstrap();
$status = $app->make(ConsoleKernel::class)->call('corex:modules:compile');

if ($status !== 0) {
    throw new RuntimeException('Installed compile failed.');
}

$activation = new ProbeActivation;
$app->instance(ModuleRegistry::class, new CompiledModuleRegistry(
    cachePath: CompiledModuleRegistry::defaultCachePath(), stateReader: $activation,
    cache: $app->make(ModuleRegistryCache::class), versionResolver: static fn (): int => $activation->epoch,
    failIfMissing: true,
));
$app->instance(PrincipalResolver::class, new ProbePrincipal);
$app->instance(OperationAuthorizer::class, new ProbePolicy);

$evidence = [];
foreach (['CoreX\\Api\\Dispatcher', 'CoreX\\Modules\\Compiler\\RegistryCompiler', 'CoreX\\CoreServiceProvider', 'Example\\Notes\\EchoNote'] as $class) {
    $file = (new ReflectionClass($class))->getFileName();

    if (! is_string($file) || ! str_starts_with(realpath($file), realpath(__DIR__.'/vendor').'/')) {
        throw new RuntimeException('Autoload resolved outside installed vendor: '.$class);
    }

    $evidence['autoload'][$class] = substr(realpath($file), strlen(realpath(__DIR__)) + 1);
}

$kernel = $app->make(HttpKernel::class);
foreach ([['notes', '{"title":"Installed"}', 200], ['undeclared', '{}', 404], ['notes', '{"title":"Installed","actor":"forged"}', 422]] as [$path, $body, $expected]) {
    $request = Request::create('/chosen/en/v1/'.$path, 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $body);
    $response = $kernel->handle($request);

    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException('Unexpected installed status: '.$response->getContent());
    }

    $evidence['statuses'][] = $response->getStatusCode();
    $kernel->terminate($request, $response);
}
$activation->enabled = false;
$activation->epoch++;
$request = Request::create('/chosen/en/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Disabled"}');
$response = $kernel->handle($request);

if ($response->getStatusCode() !== 404) {
    throw new RuntimeException('Warm disabled module executed.');
}
$kernel->terminate($request, $response);
$evidence['statuses'][] = 404;
$document = $app->make(OpenApi::class)->document('v1', 'https://example.test/chosen/en/v1');
file_put_contents(__DIR__.'/openapi.json', json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$evidence['openapi_sha256'] = hash_file('sha256', __DIR__.'/openapi.json');
$evidence['application_key_sha256'] = hash('sha256', config('app.key'));
$evidence['php'] = PHP_VERSION;
$evidence['laravel'] = Application::VERSION;
echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
