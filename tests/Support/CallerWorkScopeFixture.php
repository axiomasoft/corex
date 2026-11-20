<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

/** In-process scope for caller tests that do not exercise owner-side admission. */
final readonly class CallerWorkScopeFixture implements CurrentWorkScope, DataScopeFactory, StoredWorkIssuer, StoredWorkRunner, WorkScopeRunner
{
    /** @param ?Closure(): ConnectionInterface $connection */
    public function __construct(
        private TenantContextResolver|TenantContext $contexts,
        private ?Closure $connection = null,
        private ?DataScope $expected = null,
    ) {}

    public static function scope(string $accountId, ?string $workspaceId, string $principalId, string $restoreEpoch, ?string $bindingId = null): DataScope
    {
        return new DataScope(
            deploymentId: 'caller-test-deployment',
            accountId: $accountId,
            workspaceId: $workspaceId,
            bindingId: $bindingId ?? 'test-binding-'.$accountId,
            bindingGeneration: 1,
            accountIncarnation: 'test-incarnation-'.$accountId,
            restoreEpoch: $restoreEpoch,
            principalKind: 'user',
            principalId: $principalId,
        );
    }

    public static function forContext(TenantContext $context, string $principalId, string $restoreEpoch, ?string $bindingId = null): DataScope
    {
        return self::scope($context->account->id, $context->workspace?->id, $principalId, $restoreEpoch, $bindingId);
    }

    public static function bind(Application $app, ?DataScope $expected = null): void
    {
        $fixture = new self(
            new class($app) implements TenantContextResolver
            {
                public function __construct(private readonly Application $app) {}

                public function current(): TenantContext
                {
                    return $this->app->make(TenantContextResolver::class)->current();
                }
            },
            static fn (): ConnectionInterface => $app->make(DatabaseManager::class)->connection(),
            $expected,
        );

        $app->scoped(CurrentWorkScope::class, static fn (): CurrentWorkScope => $fixture);
        $app->scoped(DataScopeFactory::class, static fn (): DataScopeFactory => $fixture);
        $app->scoped(WorkScopeRunner::class, static fn (): WorkScopeRunner => $fixture);
        $app->scoped(StoredWorkRunner::class, static fn (): StoredWorkRunner => $fixture);
        $app->scoped(StoredWorkIssuer::class, static fn (): StoredWorkIssuer => $fixture);
    }

    public function forCurrentPrincipal(TenantContext $context): DataScope
    {
        $scope = $this->expected ?? self::forContext($context, 'operator', str_repeat('a', 64));
        $this->assert($scope);

        return $scope;
    }

    public function assert(DataScope $scope, ?ConnectionInterface $connection = null): TenantContext
    {
        $context = $this->contexts instanceof TenantContext ? $this->contexts : $this->contexts->current();

        if ($scope->accountId !== $context->account->id) {
            throw new ScopeContextMismatch('Work scope account differs from the active test account.');
        }

        if ($scope->workspaceId !== $context->workspace?->id) {
            throw new ScopeContextMismatch('Work scope workspace differs from the active test workspace.');
        }

        if (($this->expected !== null && get_object_vars($this->expected) !== get_object_vars($scope))
            || ($connection !== null && $this->connection !== null && ($this->connection)() !== $connection)) {
            throw new ScopeContextMismatch('Work scope differs from the test owner connection.');
        }

        return $context;
    }

    public function issue(DataScope $scope, string $owner, string $reference): StoredWorkProof
    {
        $this->assert($scope);

        return new StoredWorkProof($owner, $reference, $this->seal($scope, $owner, $reference));
    }

    public function runStored(DataScope $scope, StoredWorkProof $proof, Closure $work, ?OperationKey $attempt = null): mixed
    {
        if (! hash_equals($this->seal($scope, $proof->owner, $proof->reference), $proof->seal)) {
            throw new ScopeContextMismatch('Unexpected stored work proof in caller fixture.');
        }

        return $this->run($scope, $work);
    }

    private function seal(DataScope $scope, string $owner, string $reference): string
    {
        return hash_hmac('sha256', json_encode([$scope, $owner, $reference], JSON_THROW_ON_ERROR), 'caller-test-key');
    }

    public function run(DataScope $scope, Closure $work): mixed
    {
        return $work($this->assert($scope));
    }
}
