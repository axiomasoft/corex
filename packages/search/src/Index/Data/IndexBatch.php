<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Data;

use CoreX\Search\Index\Enums\IndexAction;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class IndexBatch
{
    /** @param list<IndexDocument> $documents */
    public function __construct(public IndexTarget $target, public string $submissionId, public IndexAction $action, public array $documents)
    {
        $content = in_array($action, [IndexAction::Upsert, IndexAction::DeleteRecords], true);

        if ($submissionId === '' || strlen($submissionId) > 191 || preg_match('/[\x00-\x1f\x7f]/', $submissionId) === 1
            || $content !== ($documents !== []) || count($documents) > 1000) {
            throw new InvalidArgumentException('Invalid index submission.');
        }

        $this->validateDocuments($documents);
    }

    /** @param array<array-key,mixed> $documents */
    private function validateDocuments(array $documents): void
    {
        $seen = [];
        foreach ($documents as $position => $document) {
            if ($position !== count($seen) || ! $document instanceof IndexDocument || $document->record->entity !== $this->target->entity->value
                || isset($seen[$document->record->id]) || ($this->action === IndexAction::DeleteRecords && $document->values->values !== [])) {
                throw new InvalidArgumentException('Invalid document in index batch.');
            }
            $seen[$document->record->id] = true;
        }
    }
}
