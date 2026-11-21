<?php

declare(strict_types=1);

namespace CoreX\Media\Attachments\Laravel;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Media\Attachments\Contracts\MediaAttachments;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\AttachmentRef;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlMediaAttachments implements MediaAttachments
{
    public function __construct(private ConnectionInterface $connection, private MediaPolicy $policy, private ClockInterface $clock, private MediaBlobs $blobs, private MediaOperationStore $operations, private DataScopeAuthorizer $records) {}

    public function attach(MediaContext $context, AttachmentInput $input): void
    {
        $this->blobs->assertContext($this->connection, $context);

        if (! $this->policy->allows(context: $context, action: MediaAction::Attach, subject: new MediaSubject(attachment: $input))) {
            throw new MediaAccessDenied('Media attachment is denied.');
        }

        $this->connection->transaction(function () use ($context, $input): void {
            $scope = $this->operations->currentConversionScope();

            if (! $this->records->authorize($scope, new RecordRef($input->entityHandle, $input->entityId), new FieldSelection([]), FieldUse::Write)->allowed) {
                throw new MediaAccessDenied('Related record access denied.');
            }
            ReferenceWrites::enlist($this->connection, $scope, 'axiomasoft/corex-media', 'attachment', ['entity' => $input->entityHandle]);
            $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $input->fileId)->where('account_id', $context->tenant->account->id)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                throw new MediaOperationConflict('Media file is unavailable.');
            }

            if (! $this->policy->allows(context: $context, action: MediaAction::Attach, subject: new MediaSubject(file: new MediaFileRef(id: Value::text($file->id), kind: Value::text($file->kind), mimeType: Value::text($file->mime_type), sizeBytes: Value::integer($file->size_bytes), sha256: Value::text($file->sha256), state: $file->state), attachment: $input))) {
                throw new MediaAccessDenied('Media attachment is denied.');
            }

            $this->connection->table('med_relations')->updateOrInsert(
                [...$this->operations->conversionIdentity($scope), 'file_id' => $input->fileId, 'entity_handle' => $input->entityHandle, 'entity_id' => $input->entityId, 'slot' => $input->slot],
                ['created_at' => $this->clock->now(), 'updated_at' => $this->clock->now()],
            );
        });
    }

    public function detach(MediaContext $context, AttachmentInput $input): void
    {
        $this->blobs->assertContext($this->connection, $context);

        if (! $this->policy->allows(context: $context, action: MediaAction::Detach, subject: new MediaSubject(attachment: $input))) {
            throw new MediaAccessDenied('Media detachment is denied.');
        }

        $this->connection->transaction(function () use ($context, $input): void {
            $scope = $this->operations->currentConversionScope();

            if (! $this->records->authorize($scope, new RecordRef($input->entityHandle, $input->entityId), new FieldSelection([]), FieldUse::Write)->allowed) {
                throw new MediaAccessDenied('Related record access denied.');
            }
            ReferenceWrites::enlist($this->connection, $scope, 'axiomasoft/corex-media', 'attachment', ['entity' => $input->entityHandle]);
            $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $input->fileId)->where('account_id', $context->tenant->account->id)->lockForUpdate()->first();

            if ($file === null) {
                throw new MediaOperationConflict('Media file is unavailable.');
            }

            if (! $this->policy->allows(context: $context, action: MediaAction::Detach, subject: new MediaSubject(file: new MediaFileRef(id: Value::text($file->id), kind: Value::text($file->kind), mimeType: Value::text($file->mime_type), sizeBytes: Value::integer($file->size_bytes), sha256: Value::text($file->sha256), state: Value::text($file->state)), attachment: $input))) {
                throw new MediaAccessDenied('Media detachment is denied.');
            }
            $this->connection->table('med_relations')->where([
                'file_id' => $input->fileId,
                'entity_handle' => $input->entityHandle,
                'entity_id' => $input->entityId,
                'slot' => $input->slot,
            ])->delete();
        });
    }

    /** @return list<AttachmentRef> */
    public function usages(MediaContext $context, string $fileId, int $limit = 50): array
    {
        $this->blobs->assertContext($this->connection, $context);

        if ($limit < 1 || $limit > 50) {
            throw new MediaOperationConflict('Media usage limit is invalid.');
        }

        $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->first();

        if ($file === null || ! $this->policy->allows(context: $context, action: MediaAction::Read, subject: new MediaSubject(new MediaFileRef(id: Value::text($file->id), kind: Value::text($file->kind), mimeType: Value::text($file->mime_type), sizeBytes: Value::integer($file->size_bytes), sha256: Value::text($file->sha256), state: Value::text($file->state))))) {
            throw new MediaAccessDenied('Media usage lookup is denied.');
        }

        $scope = $this->operations->currentConversionScope();
        $visible = [];
        foreach ($this->connection->table('med_relations')->where($this->operations->conversionIdentity($scope))->where('file_id', $fileId)->lazyById(100) as $row) {
            $record = new RecordRef(Value::text($row->entity_handle), Value::text($row->entity_id));
            $input = new AttachmentInput($fileId, $record->entity, $record->id, Value::text($row->slot));

            if ($this->records->authorize($scope, $record, new FieldSelection([]), FieldUse::Read)->allowed
                && $this->policy->allows($context, MediaAction::Read, new MediaSubject(attachment: $input))) {
                $visible[] = new AttachmentRef($fileId, $record->entity, $record->id, $input->slot);

                if (count($visible) === $limit) {
                    break;
                }
            }
        }

        return $visible;
    }
}
