<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\ImportExport\Contracts\RecordMapper;
use CoreX\ImportExport\Data\CanonicalRecord;
use CoreX\ImportExport\Data\MappingResult;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\ImportExport\Data\SourceRow;

final class EquipmentMapper implements RecordMapper
{
    public function map(SourceRow $row, MappingSnapshot $mapping): MappingResult
    {
        $field = $mapping->definition['serial'];
        $serial = $row->values[$field] ?? null;

        if (! is_string($serial) || preg_match('/\AEQ-[0-9]{3}\z/D', $serial) !== 1) {
            return new MappingResult('invalid', null, 'invalid_serial', '/serial');
        }
        $values = [$mapping->definition['field_id'] => $serial];

        return new MappingResult('mapped', new CanonicalRecord(
            $mapping->definition['entity'], 'equipment-fixture', InputIdentity::FIRST, $row->sourceHash, null, $values, InputIdentity::digest($values),
        ), null);
    }
}
