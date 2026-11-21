<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Foundation\Data\LifecycleConfirmation;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Data\Lifecycle\ModuleStatus;
use Packman\Modules\Enums\Lifecycle\DisableReason;

#[Internal]
final readonly class GuardedModuleLifecycle implements ModuleLifecycle
{
    public function __construct(private ModuleLifecycle $inner, private Application $app, private LifecycleFrame $origin, private bool $booted) {}

    public function enable(string $module, string $edition = 'standard', bool $cascade = false): void
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'enable', [$module, $edition, $cascade])) {
            $this->inner->enable($module, $edition, $cascade);

            return;
        }

        if (($current = $this->current()) !== null) {
            $current->enable($module, $edition, $cascade);

            return;
        }
        $this->engine()->activate($this->inner, $this, $module, 'enable', $edition, $cascade);
    }

    public function disable(string $module, DisableReason $reason, bool $cascade = false): void
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'disable', [$module, $reason, $cascade])) {
            $this->inner->disable($module, $reason, $cascade);

            return;
        }

        if (($current = $this->current()) !== null) {
            $current->disable($module, $reason, $cascade);

            return;
        }
        $engine = $this->engine();

        if ($engine->consumeTransition($module, 'disable', [$reason->value, $cascade])) {
            $frame->invoke($this, 'disable', [$module, $reason, $cascade], fn () => $this->inner->disable($module, $reason, $cascade));

            return;
        }
        $engine->direct($module, LifecycleAction::Disable, $reason, $cascade);
    }

    public function uninstall(string $module, bool $keepData = true): void
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'uninstall', [$module, $keepData])) {
            $this->inner->uninstall($module, $keepData);

            return;
        }

        if (($current = $this->current()) !== null) {
            $current->uninstall($module, $keepData);

            return;
        }
        $engine = $this->engine();

        if ($engine->consumeTransition($module, 'uninstall', [$keepData])) {
            $frame->invoke($this, 'uninstall', [$module, $keepData], fn () => $this->inner->uninstall($module, $keepData));

            return;
        }

        if ($keepData) {
            $engine->direct($module, LifecycleAction::Detach);

            return;
        }

        if (! $this->app->bound(LifecycleConfirmation::class)) {
            throw new LifecycleBlocked('Purge requires scoped plan and digest confirmation.');
        }
        $approval = $this->app->make(LifecycleConfirmation::class);
        $engine->confirmedPurge($module, $approval);
    }

    public function upgrade(string $module): void
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'upgrade', [$module])) {
            $this->inner->upgrade($module);

            return;
        }

        if (($current = $this->current()) !== null) {
            $current->upgrade($module);

            return;
        }
        $this->engine()->activate($this->inner, $this, $module, 'upgrade');
    }

    public function repair(string $module): void
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'repair', [$module])) {
            $this->inner->repair($module);

            return;
        }

        if (($current = $this->current()) !== null) {
            $current->repair($module);

            return;
        }
        $this->engine()->activate($this->inner, $this, $module, 'repair');
    }

    public function status(string $module): ModuleStatus
    {
        $frame = $this->app->make(LifecycleFrame::class);

        if ($frame->enter($this, 'status', [$module])) {
            return $this->inner->status($module);
        }

        if (($current = $this->current()) !== null) {
            return $current->status($module);
        }

        if ($frame->purgeWitness?->consumeTransition($module, 'purge-status', []) !== true) {
            $operator = $this->app->make(LifecycleOperator::class);
            $operator->authorize($operator->current(), new ModuleRef($module), 'inspect');
        }

        return $frame->invoke($this, 'status', [$module], fn () => $this->inner->status($module));
    }

    private function engine(): DatabaseLifecyclePlans
    {
        return $this->app->make(DatabaseLifecyclePlans::class);
    }

    private function current(): ?ModuleLifecycle
    {
        if ($this->booted === (! $this->app instanceof \Illuminate\Foundation\Application || $this->app->isBooted()) && $this->origin === $this->app->make(LifecycleFrame::class)) {
            return null;
        }
        $current = $this->app->make(ModuleLifecycle::class);

        return $current === $this ? null : $current;
    }
}
