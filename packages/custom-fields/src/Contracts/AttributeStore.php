<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\CustomFields\Support\AttributeWriteResult;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\RecordSelection;
use CoreX\CustomFields\Support\ValueBag;
use CoreX\CustomFields\Support\ValueBatch;
use CoreX\CustomFields\Support\ValuePatch;
use CoreX\Data\DataScope;
use CoreX\Data\RecordRef;
use CoreX\Data\WriteToken;

interface AttributeStore
{
    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag;

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult;

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch;
}
