<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\ImportDecision;
use CoreX\ImportExport\Data\TargetResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TransferSink
{
    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision;

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult;

    public function lookup(DataScope $scope, WriteToken $token): TargetResult;
}
