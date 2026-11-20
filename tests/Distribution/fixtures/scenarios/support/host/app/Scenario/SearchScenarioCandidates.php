<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use RuntimeException;

final readonly class SearchScenarioCandidates implements CandidateDriver
{
    public function __construct(private CandidateDriver $actual, private SearchScenarioTrace $trace) {}

    public function capabilities(): DriverCapabilities
    {
        return $this->actual->capabilities();
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        $page = $this->actual->candidates($request);
        $this->trace->backend[] = [
            'target' => $request->generation->uid, 'workspace' => $request->scope->workspaceId,
            'records' => array_map(static fn (Candidate $candidate): string => $candidate->record->id, $page->candidates),
        ];
        $tamper = getenv('COREX_SCENARIO_TAMPER_CANDIDATE');

        if ($tamper === false || $tamper === '') {
            return $page;
        }

        $candidate = $page->candidates[0] ?? throw new RuntimeException('The negative control needs an actual provider hit.');
        $target = $candidate->generation;
        $record = $candidate->record;
        $document = $candidate->documentId;

        if ($tamper === 'uid') {
            $other = $target->generation + 1000;
            $target = new IndexTarget($target->scope, $target->entity, $target->backend, $target->cluster,
                $other, IndexTarget::uidFor($target->scope, $target->entity, $target->backend, $target->cluster, $other), $target->mappingDigest);
        } elseif ($tamper === 'ref') {
            $record = new RecordRef($record->entity, SearchScenarioData::THIRD);
        } elseif ($tamper === 'document') {
            $document = $target->documentId(new RecordRef($record->entity, SearchScenarioData::THIRD));
        } else {
            throw new RuntimeException('Unrecognized candidate negative control.');
        }

        return new CandidatePage([new Candidate($target, $record, $document, $candidate->textWitnesses)], null);
    }
}
