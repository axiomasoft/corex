<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Contracts\RelationLookup;
use CoreX\Media\Data\AttachmentInput;
use CoreX\Media\Data\AttachmentRef;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaSubject;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

final class SqlRelationLookup implements RelationLookup
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly MediaPolicy $policy, private readonly ClockInterface $clock) {}

    public function attach(MediaContext $context, AttachmentInput $input): void
    {
        if (! $this->policy->allows($context, MediaAction::Attach, new MediaSubject(attachment: $input))) {
            throw new MediaAccessDenied('Media attachment is denied.');
        }

        $this->connection->transaction(function () use ($context, $input): void {
            $file = $this->connection->table('med_files')->where('id', $input->fileId)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                throw new MediaOperationConflict('Media file is unavailable.');
            }

            if (! $this->policy->allows($context, MediaAction::Attach, new MediaSubject(new MediaFileRef(Value::text($file->id), Value::text($file->kind), Value::text($file->mime_type), Value::integer($file->size_bytes), Value::text($file->sha256), $file->state), $input))) {
                throw new MediaAccessDenied('Media attachment is denied.');
            }

            $this->connection->table('med_relations')->updateOrInsert(
                ['file_id' => $input->fileId, 'entity_handle' => $input->entityHandle, 'entity_id' => $input->entityId, 'slot' => $input->slot],
                ['created_at' => $this->clock->now(), 'updated_at' => $this->clock->now()],
            );
        });
    }

    public function detach(MediaContext $context, AttachmentInput $input): void
    {
        if (! $this->policy->allows($context, MediaAction::Detach, new MediaSubject(attachment: $input))) {
            throw new MediaAccessDenied('Media detachment is denied.');
        }

        $this->connection->transaction(function () use ($context, $input): void {
            $file = $this->connection->table('med_files')->where('id', $input->fileId)->lockForUpdate()->first();

            if ($file === null) {
                throw new MediaOperationConflict('Media file is unavailable.');
            }

            if (! $this->policy->allows($context, MediaAction::Detach, new MediaSubject(new MediaFileRef(Value::text($file->id), Value::text($file->kind), Value::text($file->mime_type), Value::integer($file->size_bytes), Value::text($file->sha256), Value::text($file->state)), $input))) {
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
        if ($limit < 1 || $limit > 50) {
            throw new MediaOperationConflict('Media usage limit is invalid.');
        }

        $file = $this->connection->table('med_files')->where('id', $fileId)->first();

        if ($file === null || ! $this->policy->allows($context, MediaAction::Read, new MediaSubject(new MediaFileRef(Value::text($file->id), Value::text($file->kind), Value::text($file->mime_type), Value::integer($file->size_bytes), Value::text($file->sha256), Value::text($file->state))))) {
            throw new MediaAccessDenied('Media usage lookup is denied.');
        }

        return array_values($this->connection->table('med_relations')->where('file_id', $fileId)->limit($limit)->get()
            ->map(static fn (object $row): AttachmentRef => new AttachmentRef(Value::text($row->file_id), Value::text($row->entity_handle), Value::text($row->entity_id), Value::text($row->slot)))->all());
    }
}
