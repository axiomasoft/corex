<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Contracts;

use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\FieldValueBatch;
use CoreX\CustomFields\Values\Data\FieldValueSnapshot;
use CoreX\CustomFields\Values\Data\FieldWriteReceipt;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldValues
{
    public function read(DataScope $scope, RecordRef $record, FieldSelection $selection): FieldValueSnapshot;

    public function readMany(DataScope $scope, RecordSelection $records, FieldSelection $selection): FieldValueBatch;

    public function update(DataScope $scope, RecordRef $record, FieldPatch $patch, WriteToken $token): FieldWriteReceipt;
}
