<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\CustomFields\Data\AttributeWriteResult;
use CoreX\CustomFields\Data\FieldSelection;
use CoreX\CustomFields\Data\RecordSelection;
use CoreX\CustomFields\Data\ValueBag;
use CoreX\CustomFields\Data\ValueBatch;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AttributeStore
{
    public function read(DataScope $scope, RecordRef $record, FieldSelection $fields): ValueBag;

    public function write(DataScope $scope, RecordRef $record, ValuePatch $patch, WriteToken $token): AttributeWriteResult;

    public function loadMany(DataScope $scope, RecordSelection $records, FieldSelection $fields): ValueBatch;
}
