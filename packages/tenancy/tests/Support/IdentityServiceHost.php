<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support;

use Closure;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

final readonly class IdentityServiceHost implements NamedWorkPrincipal, PhysicalBindingVerifier
{
    public function __construct(private WorkScopeFixture $host, private DatabaseManager $database) {}

    public static function install(Application $app): void
    {
        $host = new self(WorkScopeFixture::install($app, 'corex.identity-sync', 'service'), $app->make(DatabaseManager::class));
        $app->scoped(NamedWorkPrincipal::class, static fn () => $host);
        $app->scoped(PhysicalBindingVerifier::class, static fn () => $host);
    }

    public function run(TenantContext $context, string $serviceId, Closure $work): mixed
    {
        if ($serviceId !== 'corex.identity-sync' || $context->workspace !== null) {
            throw new ScopeContextMismatch;
        }
        $this->host->register($context);

        return $work();
    }

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        $expected = $this->database->connection('pgsql')->table('root_accounts')->where('id', $context->account->id)->value('db_name');

        if ($connection->selectOne('select current_database() as actual')->actual !== $expected) {
            throw new ScopeContextMismatch;
        }
    }
}
