<?php

declare(strict_types=1);

namespace Equipment\Registry;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Contracts\IndexDriver;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Data\Candidate;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\IndexOutcome;
use CoreX\Search\Data\IndexSubmission;
use CoreX\Search\Data\IndexSwap;
use CoreX\Search\Data\SearchCapabilities;
use CoreX\Search\Data\SearchQuery;

final class MemoryDriver implements IndexDriver, SearchProvider
{
    /** @var array<string, array<string, null|scalar>> */
    private array $documents = [];

    public function capabilities(): SearchCapabilities
    {
        return new SearchCapabilities(fullText: true);
    }

    public function query(DataScope $scope, SearchQuery $query): CandidatePage
    {
        $matches = [];

        foreach ($this->documents as $id => $document) {
            if (($document['entity'] ?? null) !== $query->entity) {
                continue;
            }

            if (! str_contains((string) ($document['text'] ?? ''), $query->text)) {
                continue;
            }

            $matches[] = new Candidate(new RecordRef($query->entity, $id));
        }

        return new CandidatePage($matches, null, false);
    }

    public function submit(IndexBatch $batch): IndexSubmission
    {
        foreach ($batch->documents as $document) {
            $id = $document['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $this->documents[$id] = $document;
        }

        return new IndexSubmission('task-'.$batch->generation, $batch->generation);
    }

    public function status(IndexSubmission $submission): IndexOutcome
    {
        return new IndexOutcome($submission->generation === '' ? 'failed' : 'ready');
    }

    public function swap(IndexSwap $swap): IndexSubmission
    {
        return new IndexSubmission('swap-'.$swap->toGeneration, $swap->toGeneration);
    }
}
