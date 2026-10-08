<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Http\Request;

final class OperationParityTest extends TestCase
{
    private ModuleFixture $modules;

    private ContextFixture $tenants;

    private IdentityFixture $identity;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app)];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->schema(['title' => ['type' => 'string'], 'id' => ['type' => 'string'], 'actor' => ['type' => 'string'], 'account' => ['type' => 'string'], 'workspace' => ['type' => ['string', 'null']]]);
        $this->modules = new ModuleFixture(resolve(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.create', 'POST', '/notes', NoteHandler::class, 'notes.read', $schema, $schema, ['title' => ['required', 'string']]),
            ]),
        ], installedPackages: []));
        $this->tenants = new ContextFixture;
        $this->tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->identity = new IdentityFixture;
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $this->app->instance(TenantContextResolver::class, $this->tenants);
        $this->app->instance(TenancyManager::class, $this->tenants);
        NoteHandler::$calls = 0;
    }

    public function test_denied_activation_and_authorization_skip_the_handler(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $denied = $this->app->make(OperationDispatcher::class)->dispatch(
            Request::create('/chosen/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Hello"}'),
            'v1',
            'notes',
        );
        $this->modules->enabled = false;
        $this->allow();
        $inactive = $this->app->make(OperationDispatcher::class)->dispatch(
            Request::create('/chosen/v1/notes', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"title":"Hello"}'),
            'v1',
            'notes',
        );

        self::assertSame(403, $denied->getStatusCode());
        self::assertSame(404, $inactive->getStatusCode());
        self::assertSame(0, NoteHandler::$calls);
    }

    public function test_throwing_handler_restores_request_context(): void
    {
        $this->allow();
        $request = Request::create('/chosen/v1/notes/broken', 'GET', ['title' => 'Hello'], server: ['HTTP_X_REQUEST_ID' => 'request-123']);
        $this->modules = new ModuleFixture(resolve(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
                Api::operation('notes.card', 'GET', '/notes/{id}', NoteHandler::class, 'notes.read', $this->schema(), $this->schema(), ['title' => ['required', 'string']]),
            ]),
        ], installedPackages: []));
        $this->app->instance(ModuleRegistry::class, $this->modules);
        $response = $this->app->make(OperationDispatcher::class)->dispatch($request, 'v1', 'notes/broken');

        self::assertSame(500, $response->getStatusCode());
        self::assertNull($request->attributes->get(RequestContext::class));
    }

    private function allow(): void
    {
        $this->app->instance(PrincipalResolver::class, $this->identity);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return $operation->ability === 'notes.read';
            }
        });
    }

    /** @param  array<string, mixed>  $properties */
    private function schema(array $properties = ['title' => ['type' => 'string']]): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }
}
