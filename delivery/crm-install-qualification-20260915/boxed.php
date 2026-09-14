<?php

declare(strict_types=1);

use CoreX\Api\ApiController;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\ErrorBoundary;
use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__).'/vendor/autoload.php';

// Same installed immutable vendor, separate boxed application/runtime.
// This app does not register physical tenancy; it uses the owning boxed defaults.
$app = Application::configure(basePath: __DIR__)->withExceptions()
    ->withProviders([CoreServiceProvider::class, ModulesServiceProvider::class, CrmServiceProvider::class, ApiServiceProvider::class])
    ->withRouting(using: static function (): void {
        Route::get('/chosen/{version}/{path}', ApiController::class)->where('path', '.*')->middleware(ErrorBoundary::class);
    })->create();
$app->make(ConsoleKernel::class)->bootstrap();
$app->instance(PrincipalResolver::class, new class implements Principal, PrincipalResolver
{
    public function id(): string
    {
        return '00000000-0000-7000-8000-000000000001';
    }

    public function resolve(Request $request): ?Principal
    {
        return $this;
    }
});
$context = $app->make(TenantContextResolver::class)->current();
$request = Request::create('/chosen/v1/companies', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
$response = $app->make(HttpKernel::class)->handle($request);
$tables = (int) DB::selectOne("SELECT count(*) AS n FROM sqlite_master WHERE type='table'")->n;

if (! $context->isBoxed() || $response->getStatusCode() !== 403 || $tables !== 0) {
    throw new RuntimeException('Installed boxed defaults did not deny the physical CRM API profile: '.$response->getContent());
}
file_put_contents(__DIR__.'/evidence.json', json_encode(['status' => 403, 'boxed' => true, 'table_count' => 0,
    'manager' => $app->make(TenancyManager::class)::class, 'resolver' => $app->make(TenantContextResolver::class)::class,
    'response' => json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Installed boxed defaults fail closed without DDL.'.PHP_EOL;
