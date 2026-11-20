<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldValue;
use Illuminate\Support\Facades\DB;

final class FieldBackfillBarrier implements FieldConverterProvider, FieldValueConverter
{
    private bool $reached = false;

    public function __construct(private readonly FieldConverterProvider $delegate) {}

    public function id(): string
    {
        return $this->delegate->id();
    }

    public function version(): int
    {
        return $this->delegate->version();
    }

    public function converter(): FieldValueConverter
    {
        return $this;
    }

    public function convert(FieldValue $value, FieldDefinition $from, FieldDefinition $to): FieldValue
    {
        if (! $this->reached && $value->document->canonicalJson === '"r-07"') {
            $this->reached = true;
            $database = DB::connection();
            $document = $database->table('sys_attribute_documents')->where('entity', FieldScenario::ENTITY)->where('record_id', FieldScenario::RECORD_RACE)->firstOrFail();
            Scenario::barrier('backfill-read', [
                'document_revision' => $document->document_revision, 'source' => 'r-07',
                'session' => Scenario::describe($database)['session'], 'record' => FieldScenario::RECORD_RACE,
            ]);
        }

        return $this->delegate->converter()->convert($value, $from, $to);
    }
}
