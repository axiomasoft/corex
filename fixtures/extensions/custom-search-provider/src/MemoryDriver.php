<?php

declare(strict_types=1);

namespace ThirdParty\Search;

use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Projection\Data\FieldWitnesses;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use LogicException;

final class MemoryDriver implements CandidateDriver, IndexDriver
{
    /** @var array<string, array<string, IndexDocument>> */
    private array $indexes = [];

    /** @var array<string, true> */
    private array $receipts = [];

    public int $queries = 0;

    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities(text: true, scopedCorpus: true);
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        $this->queries++;
        $target = $request->generation;
        $docs = $this->indexes[$target->uid] ?? throw new LogicException('The fixture index is unavailable.');
        uasort($docs, static fn (IndexDocument $a, IndexDocument $b): int => strcmp($a->record->id, $b->record->id));
        $matches = [];

        foreach ($docs as $documentId => $doc) {
            $id = $doc->record->id;

            if (strcmp($id, $request->position ?? '') <= 0 || ($request->recordIds !== null && ! in_array($id, $request->recordIds, true))) {
                continue;
            }
            $parts = array_intersect_key($doc->values->values, array_fill_keys($request->searchFields, true));

            if ($request->text !== null && ! str_contains(implode(' ', $parts), $request->text)) {
                continue;
            }
            $witness = $request->text === null ? null : FieldWitnesses::capture($doc->values, new ProjectionSelection($request->searchFields));
            $matches[] = new Candidate($target, $doc->record, $documentId, $witness);

            if (count($matches) === $request->limit) {
                return new CandidatePage($matches, $id);
            }
        }

        return new CandidatePage($matches, null);
    }

    public function submit(IndexBatch $batch): IndexReceipt
    {
        $uid = $batch->target->uid;
        $receipt = self::taskId($batch);

        if (isset($this->receipts[$receipt])) {
            return new IndexReceipt($receipt);
        }

        if ($batch->action === IndexAction::Create) {
            $this->indexes[$uid] ??= [];
        } elseif ($batch->action === IndexAction::Drop) {
            unset($this->indexes[$uid]);
        } else {
            if (! isset($this->indexes[$uid])) {
                throw new LogicException('Create the fixture index first.');
            }

            foreach ($batch->documents as $doc) {
                $id = $batch->target->documentId($doc->record);

                if ($batch->action === IndexAction::Upsert) {
                    $this->indexes[$uid][$id] = $doc;
                } else {
                    unset($this->indexes[$uid][$id]);
                }
            }
        }
        $this->receipts[$receipt] = true;

        return new IndexReceipt($receipt);
    }

    public function lookup(IndexBatch $batch, ?IndexReceipt $receipt): IndexOutcome
    {
        $id = self::taskId($batch);

        return $receipt?->taskId === $id && isset($this->receipts[$id])
            ? IndexOutcome::Confirmed
            : IndexOutcome::Unknown;
    }

    public function canary(IndexTarget $target): bool
    {
        return isset($this->indexes[$target->uid]);
    }

    private static function taskId(IndexBatch $batch): string
    {
        return 'memory-'.hash('sha256', json_encode([
            $batch->target->uid, $batch->submissionId, $batch->action->value, $batch->documents,
        ], JSON_THROW_ON_ERROR));
    }
}
