<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Data\FieldWitnesses;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use Meilisearch\Client;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MeilisearchCandidateDriver implements CandidateDriver
{
    /** @param Closure(IndexTarget, MeilisearchAccess): Client $clients */
    public function __construct(private Closure $clients, private CurrentWorkScope $current, private string $cluster) {}

    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities(text: true, scopedCorpus: true);
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        $this->current->assert($request->scope);
        $target = $request->generation;
        $stamp = MeilisearchNamespace::fingerprint($target, $this->cluster);

        if (get_object_vars($target->scope) !== get_object_vars($request->scope)
            || $request->filters !== [] || $request->sort !== []
            || ($request->text !== null && $request->searchFields === [])
            || $request->limit < 1 || $request->limit > 1000) {
            throw SearchFeatureUnavailable::unsupported();
        }

        if ($request->recordIds === []) {
            return new CandidatePage([], null);
        }
        $filter = [MeilisearchNamespace::Stamp.' = "'.$stamp.'"'];

        if ($request->position !== null) {
            MeilisearchNamespace::recordId($request->position);
            $filter[] = MeilisearchNamespace::Order.' > "'.$request->position.'"';
        }

        if ($request->recordIds !== null) {
            $keys = array_map(static fn (string $id): string => $target->documentId(new RecordRef($target->entity->value, $id)), $request->recordIds);
            $filter[] = 'id IN '.json_encode($keys, JSON_THROW_ON_ERROR);
        }
        $parameters = [
            'filter' => $filter,
            'limit' => $request->limit,
            'attributesToRetrieve' => ['id', MeilisearchNamespace::Stamp, MeilisearchNamespace::Order],
            'sort' => [MeilisearchNamespace::Order.':asc'],
        ];

        if ($request->text !== null) {
            $parameters['attributesToSearchOn'] = array_map(MeilisearchNamespace::field(...), $request->searchFields);
            $parameters['attributesToRetrieve'][] = MeilisearchNamespace::WitnessVersion;
            array_push($parameters['attributesToRetrieve'], ...array_map(MeilisearchNamespace::witness(...), $request->searchFields));
        }
        $result = ($this->clients)($target, MeilisearchAccess::Search)->index($target->uid)->search($request->text, $parameters);

        $hits = $result->getHits();
        $candidates = [];
        $last = null;

        foreach ($hits as $hit) {
            $id = MeilisearchNamespace::recordId($hit[MeilisearchNamespace::Order] ?? null);
            $key = MeilisearchNamespace::order($id);
            $record = new RecordRef($target->entity->value, $id);
            $documentId = $hit['id'] ?? null;

            if (strcmp($key, $last ?? $request->position ?? '') <= 0 || ! is_string($documentId)
                || ! hash_equals($target->documentId($record), $documentId) || ($hit[MeilisearchNamespace::Stamp] ?? null) !== $stamp) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $last = $key;
            $digests = $request->text === null ? null : $this->textWitnesses($hit, $request->searchFields);
            $candidates[] = new Candidate($target, $record, $documentId, $digests);
        }

        return new CandidatePage($candidates, count($hits) === $request->limit ? $last : null);
    }

    /** @param array<array-key,mixed> $hit
     * @param  list<string>  $fields
     */
    private function textWitnesses(array $hit, array $fields): FieldWitnesses
    {
        if (($hit[MeilisearchNamespace::WitnessVersion] ?? null) !== 1) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $absent = new ProjectedValues([]);
        $digests = [];

        foreach ($fields as $field) {
            $digest = $hit[MeilisearchNamespace::witness($field)] ?? $absent->fieldWitness($field);

            if (! is_string($digest)) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $digests[$field] = $digest;
        }

        return new FieldWitnesses($digests);
    }
}
