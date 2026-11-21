<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Data\RemovalApproval;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface DeploymentOwners
{
    /** A held membership revision covers provisioning, offline and central owners through rollout. */
    public function freeze(ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed): string;

    /** @return list<string> Complete physical owner identities, including retained configuration. */
    public function owners(string $revision): array;

    /** Obtain a durable receipt after the old provider has fenced and scanned all four graphs at this owner. */
    public function certify(string $revision, string $owner, ModuleRef $provider): string;

    /** @param non-empty-array<string,string> $receipts */
    public function seal(string $revision, ModuleRef $provider, PlanDigest $installed, PlanDigest $proposed, array $receipts): RemovalApproval;

    /** Atomically promote the approved composition under the original membership fence. */
    public function promote(RemovalApproval $approval, PlanDigest $installed): void;
}
