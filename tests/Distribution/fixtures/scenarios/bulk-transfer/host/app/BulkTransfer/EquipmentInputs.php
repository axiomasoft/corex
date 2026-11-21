<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Data\ConflictRule;
use CoreX\ImportExport\Data\Export\ExportCursor;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\MappingSnapshot;
use CoreX\Operations\Bulk\Data\BulkContext;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Equipment\Registry\TransferBulkAdapter;
use Equipment\Registry\TransferBulkInputs;

final readonly class EquipmentInputs implements TransferBulkInputs
{
    public function __construct(private DataScopeAuthorizer $authority) {}

    public function import(BulkContext $context): array
    {
        $input = JsonDocuments::map($context->inputRef);
        $stamp = InputIdentity::stamp($input);
        $this->authorize($context, new RecordRef(InputIdentity::ENTITY, InputIdentity::FIRST), new FieldSelection([InputIdentity::SERIAL]), FieldUse::Write);

        return [
            'source' => new NdjsonEquipment($input['source'], $stamp['schema'], $stamp['codec']),
            'mapper' => new EquipmentMapper,
            'sink' => new PostgresEquipmentSink,
            'mapping' => new MappingSnapshot($stamp['mapping'], $stamp['schema'], [
                'owners' => ['equipment/registry'],
                'entity' => InputIdentity::ENTITY,
                'serial' => 'serial', 'field_id' => InputIdentity::SERIAL,
            ], InputIdentity::digest([$stamp['schema'], $stamp['mapping'], InputIdentity::ENTITY, 'serial', InputIdentity::SERIAL])),
            'conflicts' => new ConflictRule('create-only'),
            'fingerprint' => InputIdentity::fingerprint($input),
            'dryRun' => false,
        ];
    }

    public function export(BulkContext $context): array
    {
        $input = JsonDocuments::map($context->inputRef);
        $stamp = InputIdentity::stamp($input);
        $manifest = TransferBulkAdapter::manifestId($context->operationRef);
        $this->authorize($context, new RecordRef('import-export.manifest', $manifest), new FieldSelection([]), FieldUse::Export);
        foreach ([InputIdentity::FIRST, InputIdentity::SECOND] as $id) {
            $this->authorize($context, new RecordRef(InputIdentity::ENTITY, $id), new FieldSelection([InputIdentity::SERIAL]), FieldUse::Export);
        }
        $empty = str_repeat('0', 64);

        return [
            'source' => new PostgresExport,
            'manifest' => new ExportManifest(
                $manifest, 'coherent', $stamp['schema'], $context->scope->restoreEpoch,
                JsonDocuments::capture([InputIdentity::ENTITY => ['count' => 2], 'owners' => ['equipment/registry']]), $empty,
            ),
            'cursor' => new ExportCursor('0', '0', $empty),
            'fingerprint' => InputIdentity::fingerprint($input),
        ];
    }

    private function authorize(BulkContext $context, RecordRef $record, FieldSelection $fields, FieldUse $use): void
    {
        if (! $this->authority->authorize($context->scope, $record, $fields, $use)->allowed) {
            throw new ApiError(OperationsErrorCode::AccessDenied, 403, 'Current equipment grant denies transfer.');
        }
    }
}
