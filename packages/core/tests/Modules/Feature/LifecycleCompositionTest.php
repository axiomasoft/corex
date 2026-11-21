<?php

declare(strict_types=1);

namespace CoreX\Tests\Modules\Feature;

use CoreX\Context\Data\ScopePrincipal;
use CoreX\CoreServiceProvider;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Orchestra\Testbench\TestCase;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Data\Lifecycle\ModuleStatus;
use Packman\Modules\Enums\Lifecycle\DisableReason;
use Packman\Modules\Enums\Lifecycle\ModuleState;
use Packman\Modules\ModulesServiceProvider;

final class CompositionHost implements ModuleLifecycle
{
    public int $reads = 0;

    public string $edition = 'original';

    public function status(string $module): ModuleStatus
    {
        $this->reads++;

        return new ModuleStatus($module, ModuleState::Disabled, '1', '1', false, $this->edition);
    }

    public function enable(string $module, string $edition = 'standard', bool $cascade = false): void
    {
        throw new LogicException;
    }

    public function disable(string $module, DisableReason $reason, bool $cascade = false): void
    {
        throw new LogicException;
    }

    public function uninstall(string $module, bool $keepData = true): void
    {
        throw new LogicException;
    }

    public function upgrade(string $module): void
    {
        throw new LogicException;
    }

    public function repair(string $module): void
    {
        throw new LogicException;
    }
}

final class CompositionState
{
    public int $constructed = 0;

    public bool $refused = false;

    public ?CompositionHost $host = null;

    public ?ModuleLifecycle $reader = null;

    public ?ModuleLifecycle $observed = null;

    public function refresh(ModuleLifecycle $lifecycle): void
    {
        $this->observed = $lifecycle;
    }
}

final class EarlyLifecycleConsumer extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CompositionState::class);
        $this->app->booted(function (Application $app): void {
            $state = $app->make(CompositionState::class);
            $scope = WorkScopeFixture::install($app, 'observer')->scope();
            $grant = false;
            Gate::define('corex.lifecycle.inspect', static function (ScopePrincipal $principal, DataScope $current, ModuleRef $module) use (&$grant, $scope): bool {
                return $grant && $principal->id === 'observer' && get_object_vars($current) === get_object_vars($scope) && $module->package === 'fixture/lifetime';
            });
            $reader = $state->reader ?? throw new LogicException;

            try {
                $reader->status('fixture/lifetime');
            } catch (AuthorizationException) {
                $state->refused = true;
            }
            $grant = true;

            if ($reader->status('fixture/lifetime')->edition !== 'late mutable host') {
                throw new LogicException('Early public lifecycle use lost the final provider.');
            }
        });
    }
}

final class FinalLifecycleHost extends ServiceProvider
{
    public function boot(): void
    {
        $state = $this->app->make(CompositionState::class);
        // A warm reader already exists before the host finalizes its public alias.
        $state->reader = $this->app->make(ModuleLifecycle::class);
        $this->app->singleton('fixture.lifecycle.final', static function () use ($state): CompositionHost {
            $state->constructed++;
            $host = new CompositionHost;
            $host->edition = 'late mutable host';

            return $state->host = $host;
        });
        $this->app->alias('fixture.lifecycle.final', ModuleLifecycle::class);
        $this->app->refresh(ModuleLifecycle::class, $state, 'refresh');
    }
}

final class LifecycleCompositionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EarlyLifecycleConsumer::class, CoreServiceProvider::class, ModulesServiceProvider::class, FinalLifecycleHost::class];
    }

    public function test_warm_reader_and_native_refresh_preserve_final_mutable_host_at_first_public_use(): void
    {
        $state = $this->app->make(CompositionState::class);
        self::assertTrue($state->refused);
        self::assertSame(1, $state->constructed);
        self::assertSame(1, $state->host?->reads);
        self::assertSame($this->app->make(ModuleLifecycle::class), $state->observed);
        $state->host->edition = 'after first use';
        $this->app->forgetScopedInstances();
        self::assertSame('after first use', $state->reader?->status('fixture/lifetime')->edition);
        self::assertSame(2, $state->host->reads);
        self::assertSame(1, $state->constructed);
    }
}
