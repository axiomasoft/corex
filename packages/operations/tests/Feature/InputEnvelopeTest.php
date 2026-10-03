<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Internal\Http\ApiController;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Enums\InputEnvelope;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Testing\NoteHandler;
use CoreX\Operations\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InputEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $declarations = [];
        foreach (['GET', 'POST'] as $method) {
            $declarations[] = Api::operation(
                id: 'notes.'.strtolower($method), method: $method, path: '/notes', handler: NoteHandler::class, ability: 'notes.read',
                requestSchema: ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'], 'properties' => ['title' => ['type' => 'string']]],
                responseSchema: ['type' => 'object'], rules: ['title' => ['required', 'string']],
            );
        }
        $registry = $this->app->make(RegistryCompiler::class)->compile([
            ModuleManifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends($declarations),
        ], installedPackages: []);
        foreach ($registry->api as $declaration) {
            self::assertSame(InputEnvelope::forMethod($declaration->method), OperationFactory::fromDeclaration($declaration)->inputEnvelope);
        }
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($registry));
        $tenants = new ContextFixture;
        $tenants->initialize(new AccountRef('A', 'a', 'active', [], []));
        $this->app->instance(TenantContextResolver::class, $tenants);
        $this->app->instance(TenancyManager::class, $tenants);
        $this->app->instance(PrincipalResolver::class, new IdentityFixture);
        $this->app->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
        {
            public function allows(Operation $operation, RequestContext $context): bool
            {
                return true;
            }
        });
        NoteHandler::$calls = 0;
        $this->app['router']->match(['GET', 'POST'], '/strict/{version}/{path}', ApiController::class);
    }

    public static function requests(): array
    {
        return [
            'query' => ['GET', '?title=x', '', 'application/json', 200, null, null],
            'strict query' => ['GET', '?title=x&color=red', '', 'application/json', 422, OperationsErrorCode::UndeclaredFields, ['color']],
            'media type' => ['POST', '', '{"title":"x"}', 'text/plain', 415, OperationsErrorCode::UnsupportedMediaType, null],
            'query before media type' => ['POST', '?a=1', '{', 'text/plain', 422, OperationsErrorCode::InputEnvelopeInvalid, null],
            'query with body' => ['POST', '?a=1', '{"title":"x"}', 'application/json', 422, OperationsErrorCode::InputEnvelopeInvalid, null],
            'array' => ['POST', '', '[1,2]', 'application/json', 422, OperationsErrorCode::InputEnvelopeInvalid, null],
            'malformed' => ['POST', '', '{', 'application/json', 422, OperationsErrorCode::InputEnvelopeInvalid, null],
            'scalar' => ['POST', '', 'null', 'application/json', 422, OperationsErrorCode::InputEnvelopeInvalid, null],
            'strict body' => ['POST', '', '{"title":"x","b":1,"a":2}', 'application/json', 422, OperationsErrorCode::UndeclaredFields, ['a', 'b']],
            'object' => ['POST', '', '{"title":"x"}', 'application/json', 200, null, null],
            'field validation' => ['POST', '', '{}', 'application/json', 422, OperationsErrorCode::ValidationFailed, null],
        ];
    }

    #[DataProvider('requests')]
    public function test_input_envelope_and_strictness(string $method, string $query, string $body, string $contentType, int $status, ?OperationsErrorCode $code, ?array $fields): void
    {
        $response = $this->call($method, '/strict/v1/notes'.$query, server: ['CONTENT_TYPE' => $contentType], content: $body);
        $response->assertStatus($status);

        if ($code === null) {
            self::assertSame(1, NoteHandler::$calls);
            self::assertArrayNotHasKey('error', $response->json());

            return;
        }
        self::assertSame(0, NoteHandler::$calls);
        $definition = $this->app->make(ProblemRegistry::class)->get($code);
        self::assertSame(ErrorCategory::Validation, $definition->category);
        $response->assertHeader('Content-Type', 'application/problem+json')->assertJsonPath('code', $code->value())
            ->assertJsonPath('type', $definition->type)->assertJsonPath('title', $definition->title)->assertJsonPath('category', 'validation');

        if ($fields !== null) {
            $response->assertJsonPath('details.fields', $fields);
        }
    }
}
