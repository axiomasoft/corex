<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Internal\SignedCursorCodec;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Testing\IdentityFixture;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\WebhookManagement;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use CoreX\Webhooks\Laravel\WebhookController;
use CoreX\Webhooks\Laravel\WebhookHttpRegistrar;
use CoreX\Webhooks\Laravel\WebhookOperationDeclarations;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Mockery;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Contracts\Migration\SchemaSets;

final class WebhookManagementTest extends TestCase
{
    private Connection $connection;

    private FrozenClock $clock;

    private ContextFixture $contexts;

    private WebhookManagement $management;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-operations.routes.middleware', [TrustedWebhookClient::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $this->connection = DB::connection('api_tenant');

        $this->migrateSchemaSets(['axiomasoft/corex-webhooks:tenant'], resolve(ApiSchemaConnections::class)->tenant()->getName());
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-09-26T15:00:00.000000+00:00'));
        $this->contexts = new ContextFixture;
        $this->contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $restorer = new CallerWorkScopeFixture($this->contexts);
        $this->management = new WebhookManagement(
            connection: $this->connection,
            contexts: $restorer,
            cursors: new SignedCursorCodec($this->clock, $this->keyring()),
        );
    }

    public function test_delivery_history_is_bounded_scoped_and_cursor_bound_to_tenant_client_and_filter(): void
    {
        $scopeA = $this->scope('account-a');
        $this->seedSubscription('subscription-a');
        $this->seedDelivery('delivery-a', 'subscription-a', $scopeA, $this->clock->now()->sub(new DateInterval('PT2S')));
        $this->seedDelivery('delivery-b', 'subscription-a', $scopeA, $this->clock->now()->sub(new DateInterval('PT1S')));

        $first = $this->management->deliveries($scopeA, 'client-a', ['status' => 'dead'], null, 1);

        self::assertSame(['delivery-a'], array_column($first['items'], 'id'));
        self::assertIsString($first['next']);
        self::assertArrayNotHasKey('body', $first['items'][0]);
        self::assertArrayNotHasKey('endpoint_snapshot', $first['items'][0]);

        $second = $this->management->deliveries($scopeA, 'client-a', ['status' => 'dead'], $first['next'], 1);
        self::assertSame(['delivery-b'], array_column($second['items'], 'id'));
        self::assertNull($second['next']);

        foreach ([
            [$scopeA, 'client-b', ['status' => 'dead']],
            [$scopeA, 'client-a', ['status' => 'succeeded']],
        ] as [$scope, $client, $filters]) {
            try {
                $this->management->deliveries($scope, $client, $filters, $first['next'], 1);
                self::fail('A cursor escaped its declared client/filter scope.');
            } catch (ApiError $error) {
                self::assertSame('operations.cursor_invalid', $error->errorCode);
            }
        }

        $this->contexts->initialize(new AccountRef('account-b', 'account-b', 'active', [], []));

        try {
            $this->management->deliveries($this->scope('account-b'), 'client-a', ['status' => 'dead'], $first['next'], 1);
            self::fail('A cursor escaped its tenant scope.');
        } catch (ApiError $error) {
            self::assertSame('operations.cursor_invalid', $error->errorCode);
        }
    }

    public function test_detail_attempts_and_subscription_projection_are_public_safe(): void
    {
        $scope = $this->scope('account-a');
        $this->seedSubscription('subscription-safe');
        $this->seedDelivery('delivery-safe', 'subscription-safe', $scope, $this->clock->now());
        $this->connection->table('sys_api_webhook_attempts')->insert([
            'id' => 'attempt-safe', 'delivery_id' => 'delivery-safe', 'attempt' => 1,
            'lease_owner' => 'worker-safe', 'started_at' => $this->clock->now(), 'finished_at' => $this->clock->now(),
            'outcome' => 'dead', 'http_status' => 422, 'duration_ms' => 7, 'error_code' => 'http_permanent',
        ]);

        $detail = $this->management->delivery($scope, 'delivery-safe');
        $attempts = $this->management->attempts($scope, 'delivery-safe');
        $subscriptions = $this->management->subscriptions($scope);

        self::assertSame('delivery-safe', $detail['id'] ?? null);
        self::assertSame('http_permanent', $attempts[0]['error_code'] ?? null);
        self::assertSame(1, $subscriptions[0]['key_ref_count'] ?? null);

        $wire = JsonCanonical::encode([$detail, $attempts, $subscriptions]);
        foreach (['"body":', 'signature', 'secret', 'key_refs', 'token=private'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $wire);
        }
    }

    public function test_literal_routes_forward_every_operation_through_the_shared_dispatcher(): void
    {
        (new WebhookHttpRegistrar)->boot($this->app);
        $routes = array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with((string) $route->getName(), 'corex.api.webhooks.'),
        ));
        $replay = array_find(
            $routes,
            static fn (Route $route): bool => $route->getName() === 'corex.api.webhooks.delivery.replay',
        );

        self::assertCount(9, $routes);
        self::assertInstanceOf(Route::class, $replay);
        self::assertSame(
            WebhookController::class.'@forward',
            $replay->getAction('uses'),
        );
        self::assertSame(['POST'], $replay->methods());
    }

    public function test_mounted_replay_requires_an_idempotency_key_and_refuses_legacy_majors(): void
    {
        $registry = resolve(ModuleCompilation::class)->compile([
            ModuleManifest::make('example/notes')->title('Webhooks')->tablePrefix('whk')->extends(WebhookOperationDeclarations::all()),
        ], installedPackages: []);
        $this->app->instance(ModuleRegistry::class, new ModuleFixture($registry));
        $this->app->instance(TenantContextResolver::class, $this->contexts);
        $this->app->instance(TenancyManager::class, $this->contexts);
        $this->app->instance(PrincipalResolver::class, new IdentityFixture);
        foreach ([OperationAuthorizer::class, ClientScopeAuthorizer::class] as $port) {
            $authorizer = Mockery::mock($port);
            $authorizer->shouldReceive('allows')->andReturnTrue();
            $this->app->instance($port, $authorizer);
        }

        (new WebhookHttpRegistrar)->boot($this->app);
        $this->postJson('/api/v2/webhook-deliveries/delivery-a/replay', ['audit_reason' => 'verify'])
            ->assertJsonPath('code', 'operations.idempotency_key_required')->assertUnprocessable();
        $this->postJson('/api/v1/webhook-deliveries/delivery-a/replay')->assertNotFound();
        $this->postJson('/api/v3/webhook-deliveries/delivery-a/replay')->assertNotFound();
    }

    public function test_route_contract_requires_actor_scope_and_transactional_idempotency(): void
    {
        $replay = $this->operation(
            method: 'POST',
            path: '/webhook-deliveries/{id}/replay',
            ability: 'webhooks.replay',
            scopes: ['webhooks.replay'],
            idempotency: IdempotencyRequirement::Required,
            effect: 'tenant_transactional',
        );
        WebhookController::assertOperationContract($replay);

        foreach ([
            $this->operation('POST', '/webhook-deliveries/{id}/replay', 'webhooks.manage', ['webhooks.manage'], IdempotencyRequirement::Required, 'tenant_transactional'),
            $this->operation('POST', '/webhook-deliveries/{id}/replay', 'webhooks.replay', ['webhooks.replay'], IdempotencyRequirement::Forbidden, 'tenant_transactional'),
            $this->operation('POST', '/webhook-deliveries/{id}/replay', 'webhooks.replay', ['webhooks.replay'], IdempotencyRequirement::Required, null),
        ] as $invalid) {
            try {
                WebhookController::assertOperationContract($invalid);
                self::fail('Unsafe webhook operation metadata was accepted.');
            } catch (ApiError $error) {
                self::assertSame('core.internal_error', $error->errorCode);
            }
        }
    }

    private function seedSubscription(string $id): void
    {
        $this->connection->table('sys_api_webhook_subscriptions')->insert([
            'id' => $id, 'revision' => 0, 'major' => 'v2', 'topic' => 'orders.placed', 'topic_version' => 1,
            'endpoint_url' => 'https://hooks.example/receiver?token=private', 'status' => 'active',
            'max_pii_class' => 'personal', 'key_refs' => '["current"]',
            'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
        ]);
    }

    private function seedDelivery(string $id, string $subscription, DataScope $scope, DateTimeImmutable $createdAt): void
    {
        $body = JsonCanonical::encode(['event_id' => $id, 'data' => ['secret' => 'never-public']]);
        $this->connection->table('sys_api_webhook_deliveries')->insert(WebhookRows::original($scope, [
            'id' => $id, 'subscription_id' => $subscription, 'subscription_revision' => 0,
            'event_id' => 'event-'.$id, 'topic' => 'orders.placed', 'topic_version' => 1, 'major' => 'v2',
            'endpoint_snapshot' => 'https://hooks.example/receiver?token=private',
            'scope' => JsonCanonical::encode(resolve(DataScopeCodec::class)->encode($scope)),
            'body' => $body, 'body_hash' => hash('sha256', $body), 'status' => 'dead', 'attempt' => 1,
            'replay_count' => 0, 'lease_owner' => null, 'lease_until' => null, 'next_attempt_at' => $createdAt,
            'created_at' => $createdAt, 'finished_at' => $createdAt, 'expires_at' => $createdAt->add(new DateInterval('P7D')),
            'last_error' => 'http_permanent',
        ]));
    }

    private function scope(string $account): DataScope
    {
        return CallerWorkScopeFixture::scope($account, null, 'actor-a', hash('sha256', 'webhook-management'));
    }

    private function keyring(): CursorKeyring
    {
        return new class implements CursorKeyring
        {
            public function currentKeyId(): string
            {
                return 'current';
            }

            public function ttlSeconds(): int
            {
                return 900;
            }

            public function verifyingSecret(string $keyId): ?string
            {
                return $keyId === 'current' ? str_repeat('c', 32) : null;
            }
        };
    }

    /** @param list<string> $scopes */
    private function operation(
        string $method,
        string $path,
        string $ability,
        array $scopes,
        IdempotencyRequirement $idempotency,
        ?string $effect,
    ): Operation {
        return new Operation(
            module: 'axiomasoft/corex-operations',
            id: 'webhook-contract-fixture',
            version: 'v2',
            method: $method,
            path: $path,
            ability: $ability,
            handler: WebhookContractHandler::class,
            requestSchema: JsonDocuments::capture([]),
            responseSchema: JsonDocuments::capture([]),
            inputRules: [],
            successStatus: 200,
            mode: $method === 'GET' ? OperationMode::Query : OperationMode::Command,
            requiredScopes: $scopes,
            idempotency: $idempotency,
            effectMode: $effect,
        );
    }

    private function catalogue(): WebhookTopicCatalogue
    {
        $schema = ['id' => 'string'];

        return new WebhookTopicCatalogue(
            MessagingFixtures::contracts([
                new MessageContract('orders.placed', 1, MessageClass::IntegrationEvent, 'orders/api', PiiClass::Personal, 'webhooks'),
            ]),
            [new WebhookTopic('orders.placed', 1, 'v2', $schema, hash('sha256', JsonCanonical::encode($schema)), 'personal', 'orders.placed')],
            ['orders.placed' => new ManagementProjector],
        );
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(): void
    {
        $this->app->forgetInstance(ApiSchemaConnections::class);
        $this->app->forgetInstance(SchemaSets::class);
        $this->app->forgetInstance(SchemaMigrator::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}

final class ManagementProjector implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return [];
    }
}

final class WebhookContractHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}

final class TrustedWebhookClient
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->attributes->set('corex.client_id', 'client-a');

        return $next($request);
    }
}
