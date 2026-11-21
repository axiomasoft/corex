<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Contracts;

use CoreX\Data\Data\JsonDocument;
use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Bulk\Data\BulkFailurePage;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface BulkOwner
{
    public function admit(BulkContext $context): void;

    public function result(BulkContext $context, BulkOwnerRef $ref): ?JsonDocument;

    public function failures(BulkContext $context, BulkOwnerRef $ref, ?string $after, int $limit): ?BulkFailurePage;
}
