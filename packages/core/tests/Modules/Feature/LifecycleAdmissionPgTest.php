<?php

declare(strict_types=1);

namespace CoreX\Tests\Modules\Feature;

use Closure;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tenant\Exceptions\Runtime\StaleHandle;

#[Group('pg')]
final class LifecycleAdmissionPgTest extends TestCase
{
    public function test_independent_coordinator_remains_exclusive_over_native_workspace_generation_changes(): void
    {
        $tenant = getenv('PGSQL_DATABASE');
        $controlName = getenv('PGSQL_CONTROL_DATABASE') ?: preg_replace('/_test$/', '_control_test', (string) $tenant);
        self::assertTrue(app()->environment('testing') && is_string($tenant) && str_ends_with($tenant, '_test')
            && is_string($controlName) && str_ends_with($controlName, '_test') && $tenant !== $controlName);
        $schema = 'lifecycle_test_'.bin2hex(random_bytes(8));
        config(['database.connections.pgsql.database' => $controlName,
            'database.connections.lifecycle-control' => [...config('database.connections.pgsql'), 'database' => $controlName],
            'corex.scope.deployment_id' => 'cloud-test', 'corex.scope.control_connection' => 'lifecycle-control']);
        DB::purge('pgsql');
        $central = DB::connection('pgsql');
        $control = DB::connection('lifecycle-control');
        self::assertSame($controlName, $central->scalar('select current_database()'));
        self::assertSame($controlName, $control->scalar('select current_database()'));
        $central->statement('CREATE SCHEMA '.$schema);
        $manager = resolve(TenancyManager::class);

        try {
            $central->statement('SET search_path TO '.$schema);
            $control->statement('SET search_path TO '.$schema);
            $central->statement('CREATE TABLE root_accounts (id text primary key, db_name text not null)');
            $central->table('root_accounts')->insert(['id' => 'A', 'db_name' => $tenant]);
            ScopeControlSchema::install($control);
            $this->app->forgetInstance(ScopeOptions::class);
            $this->app->instance(ControlStoreIsolation::class, new readonly class($control) implements ControlStoreIsolation
            {
                public function __construct(private ConnectionInterface $control) {}

                public function verify(ConnectionInterface $control, string $deploymentId): void
                {
                    if ($control !== $this->control || $deploymentId !== 'cloud-test') {
                        throw new RuntimeException('Unexpected independent control owner.');
                    }
                }
            });
            $controlOwner = resolve(DatabaseScopeControl::class);
            $epoch = hash('sha256', 'cloud-test-epoch');
            $controlOwner->register('A', new PhysicalBinding('cloud-test', 'test-account:A', 1, 'incarnation-A', $epoch));
            $account = new AccountRef('A', 'account-a', 'active', [], []);
            $scope = new DataScope('cloud-test', 'A', null, 'test-account:A', 1, 'incarnation-A', $epoch, 'user', 'operator');
            $manager->initialize($account);
            $old = DB::connection();
            self::assertSame($tenant, $old->scalar('select current_database()'));
            $controlOwner->withLifecycleLock($scope, function (Closure $verify) use ($manager, $account, $scope, $controlOwner, $tenant): void {
                foreach (['W1', 'W2'] as $id) {
                    $manager->runFor($account, new WorkspaceRef($id, $id, 'workspace', '/', null), function () use ($verify, $tenant, $manager, $id, $controlOwner, $scope): void {
                        self::assertSame($tenant, DB::connection()->scalar('select current_database()'));
                        self::assertSame($id, $manager->context()?->workspace?->id);
                        $verify();

                        try {
                            $controlOwner->withLifecycleLock($scope, static fn () => self::fail('Concurrent coordinator admitted.'));
                        } catch (LifecycleBlocked $blocked) {
                            self::assertStringContainsString('Concurrent physical', $blocked->getMessage());
                        }
                    });
                }
                $verify();
                self::assertNull($manager->context()?->workspace);
                self::assertSame($tenant, DB::connection()->scalar('select current_database()'));
            });
            self::assertSame('reconciled', $control->table('corex_scope_tickets')->sole()->state);
            self::assertSame('next holder', $controlOwner->withLifecycleLock($scope, static fn (): string => 'next holder'));
            $this->expectException(StaleHandle::class);
            $old->scalar('select current_database()');
        } finally {
            $manager->end();
            $central->statement('DROP SCHEMA '.$schema.' CASCADE');
        }
    }
}
