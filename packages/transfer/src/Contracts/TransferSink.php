<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\ConflictRule;
use CoreX\Transfer\Data\ImportDecision;
use CoreX\Transfer\Data\TargetResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TransferSink
{
    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision;

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult;

    public function lookup(DataScope $scope, WriteToken $token): TargetResult;
}
