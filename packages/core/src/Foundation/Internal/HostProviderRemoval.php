<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Foundation\Contracts\DeploymentOwners;
use CoreX\Foundation\Contracts\ProviderRemovalGate;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Data\RemovalApproval;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;

#[Internal]
final readonly class HostProviderRemoval implements ProviderRemovalGate
{
    public function __construct(private Application $app, private LifecycleOperator $operator) {}

    public function prepare(ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed): RemovalApproval
    {
        $this->operator->authorize(scope: $this->operator->current(), target: $provider, ability: 'remove-provider', digest: $proposed);
        $host = $this->host();
        $revision = $host->freeze(provider: $provider, installed: $installed, proposed: $proposed);
        $owners = $host->owners($revision);

        if ($revision === '' || $owners === [] || count(array_unique($owners)) !== count($owners)) {
            throw new LifecycleBlocked('Incomplete deployment membership.');
        }
        sort($owners, SORT_STRING);
        $receipts = [];
        foreach ($owners as $owner) {
            $this->operator->authorize(scope: $this->operator->current(), target: $provider, ability: 'remove-provider', digest: $proposed);
            $receipt = $host->certify(revision: $revision, owner: $owner, provider: $provider);

            if ($owner === '' || $receipt === '') {
                throw new LifecycleBlocked('Missing physical owner receipt.');
            }
            $receipts[$owner] = $receipt;
        }
        $this->operator->authorize(scope: $this->operator->current(), target: $provider, ability: 'remove-provider', digest: $proposed);
        $approval = $host->seal(revision: $revision, provider: $provider, installed: $installed, proposed: $proposed, receipts: $receipts);

        if ($approval->provider->package !== $provider->package || $approval->installed->value !== $installed->value || $approval->proposed->value !== $proposed->value || $approval->receipt === '') {
            throw new LifecycleBlocked('Deployment receipt does not cover the requested composition.');
        }

        return $approval;
    }

    public function consume(RemovalApproval $approval, PlanDigest $installed): void
    {
        $this->operator->authorize(scope: $this->operator->current(), target: $approval->provider, ability: 'remove-provider', digest: $approval->proposed);

        if ($approval->installed->value !== $installed->value || $approval->receipt === '') {
            throw new LifecycleBlocked('Deployment composition changed.');
        }
        $this->host()->promote(approval: $approval, installed: $installed);
    }

    private function host(): DeploymentOwners
    {
        if (! $this->app->bound(DeploymentOwners::class)) {
            throw new LifecycleBlocked('Deployment membership and promotion authority unavailable.');
        }

        return $this->app->make(DeploymentOwners::class);
    }
}
