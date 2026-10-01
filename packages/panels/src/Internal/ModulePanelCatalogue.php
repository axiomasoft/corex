<?php

declare(strict_types=1);

namespace CoreX\Panels\Internal;

use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Contracts\PanelProvider;
use CoreX\Panels\Data\EntityReference;
use CoreX\Panels\Data\PanelAccess;
use CoreX\Panels\Data\PanelContext;
use CoreX\Panels\Data\PanelDescriptor;
use CoreX\Panels\Data\PanelReference;
use LogicException;
use Throwable;

/** @internal */
final class ModulePanelCatalogue implements PanelCatalogue
{
    /** @var array<string, PanelProvider> */
    private array $providers = [];

    /** @var array<string, PanelDescriptor> */
    private array $descriptors = [];

    /** @param iterable<PanelProvider> $providers */
    public function __construct(
        iterable $providers,
        private readonly EntityRegistry $entities,
        private readonly ModuleRegistry $modules,
    ) {
        foreach ($providers as $provider) {
            $descriptor = $provider->descriptor();
            $key = $descriptor->key;

            if (isset($this->providers[$key])) {
                throw new LogicException("Duplicate panel provider key [{$key}].");
            }

            $this->providers[$key] = $provider;
            $this->descriptors[$key] = $descriptor;
        }
    }

    public function forEntity(PanelContext $context, EntityReference $entity): array
    {
        $definition = $this->activeEntity($context, $entity);

        if ($definition === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (PanelDescriptor $descriptor): ?PanelDescriptor => $this->visibleDescriptor($descriptor, $context, $definition->capabilities),
            $this->descriptors,
        )));
    }

    public function access(PanelContext $context, PanelReference $panel): PanelAccess
    {
        $provider = $this->providers[$panel->providerKey] ?? null;

        $descriptor = $this->descriptors[$panel->providerKey] ?? null;

        if ($provider === null || $descriptor === null || ! $this->providerActive($descriptor, $context)) {
            return PanelAccess::deny('provider_unavailable');
        }

        if ($panel->parent !== null) {
            $parent = $this->activeEntity($context, $panel->parent);

            if ($parent === null || ! $this->supports($descriptor, $parent->capabilities)) {
                return PanelAccess::deny('entity_unavailable');
            }
        }

        try {
            $access = $provider->access($context, $panel);
        } catch (Throwable) {
            return PanelAccess::deny('policy_unavailable');
        }

        $supported = $descriptor->supportedActions;

        if (array_diff($access->actions, $supported) !== []) {
            return PanelAccess::deny('invalid_policy_result');
        }

        return $access;
    }

    /** @param list<Capability> $capabilities */
    private function visibleDescriptor(PanelDescriptor $descriptor, PanelContext $context, array $capabilities): ?PanelDescriptor
    {
        if (! $this->providerActive($descriptor, $context)) {
            return null;
        }

        return $this->supports($descriptor, $capabilities) ? $descriptor : null;
    }

    private function providerActive(PanelDescriptor $descriptor, PanelContext $context): bool
    {
        return $this->modules->isActive($descriptor->owningModule, $context->tenant);
    }

    /** @param list<Capability> $capabilities */
    private function supports(PanelDescriptor $descriptor, array $capabilities): bool
    {
        if ($descriptor->requiredCapability === null) {
            return true;
        }

        $capability = Capability::tryFrom($descriptor->requiredCapability);

        return $capability !== null && in_array($capability, $capabilities, true);
    }

    private function activeEntity(PanelContext $context, EntityReference $entity): ?EntityDefinition
    {
        if (! $this->entities->has($entity->handle)) {
            return null;
        }

        $definition = $this->entities->get($entity->handle);

        return in_array($definition, $this->entities->all($context->tenant), true) ? $definition : null;
    }
}
