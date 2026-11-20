<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\StoredWorkProof;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface StoredWorkIssuer
{
    public function issue(DataScope $scope, string $owner, string $reference): StoredWorkProof;
}
