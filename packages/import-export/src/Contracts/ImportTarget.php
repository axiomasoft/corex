<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\ImportExport\Support\CanonicalRecord;
use CoreX\ImportExport\Support\ConflictRule;
use CoreX\ImportExport\Support\ImportDecision;
use CoreX\ImportExport\Support\TargetResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;

interface ImportTarget
{
    public function preview(DataScope $scope, CanonicalRecord $record, ConflictRule $rule): ImportDecision;

    public function apply(DataScope $scope, CanonicalRecord $record, ConflictRule $rule, WriteToken $token): TargetResult;

    public function lookup(DataScope $scope, WriteToken $token): TargetResult;
}
