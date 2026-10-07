<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Events;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\CompiledRegistry;
use Illuminate\Events\Dispatcher;

final class ManifestListeners
{
    /** @var array<string, true> */
    private array $registered = [];

    public function __construct(
        private readonly Dispatcher $events,
        private readonly ModuleRegistry $modules,
        private readonly TenantContextResolver $context,
    ) {}

    public function register(CompiledRegistry $registry): void
    {
        foreach ($registry->listeners as $module => $events) {
            foreach ($events as $event => $listeners) {
                foreach (array_unique($listeners) as $listener) {
                    $key = $module.'|'.$event.'|'.$listener;

                    if (isset($this->registered[$key])) {
                        continue;
                    }
                    $this->registered[$key] = true;
                    $invoke = $this->events->makeListener($listener);
                    $this->events->listen($event, function (...$payload) use ($module, $event, $listener, $invoke): mixed {
                        try {
                            $context = $this->context->current();
                        } catch (CentralContextException) {
                            return null;
                        }

                        if (! $this->modules->isActive(name: $module, ctx: $context)
                            || ! in_array($listener, $this->modules->compiled()->listeners[$module][$event] ?? [], true)) {
                            return null;
                        }

                        return $invoke($event, $payload);
                    });
                }
            }
        }
    }
}
