<?php

declare(strict_types=1);

namespace CoreX\Attributes\Contracts;

use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValueBag;
use CoreX\Attributes\Support\ValueBatch;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Support\WriteResult;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;

interface AttributeStore
{
    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag;

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): WriteResult;

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch;
}
