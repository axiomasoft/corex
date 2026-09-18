<?php

declare(strict_types=1);

namespace CoreX\Transfer\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;
use CoreX\Transfer\Data\CanonicalRecord;
use CoreX\Transfer\Data\ConflictRule;
use CoreX\Transfer\Data\ImportDecision;
use CoreX\Transfer\Data\TargetResult;

interface ImportTarget
{
    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision;

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult;

    public function lookup(DataScope $scope, WriteToken $token): TargetResult;
}
