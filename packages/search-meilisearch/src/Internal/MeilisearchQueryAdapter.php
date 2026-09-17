<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Support\Candidate;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\SearchCapabilities;
use CoreX\Search\Support\SearchQuery;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;
use InvalidArgumentException;
use Meilisearch\Client;

final class MeilisearchQueryAdapter implements SearchProvider
{
    public function __construct(
        private Client $client,
        private string $indexUid,
    ) {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,255}\z/D', $indexUid) !== 1) {
            throw new InvalidArgumentException('Meilisearch index UID must be configured by the consumer.');
        }
    }

    public function capabilities(): SearchCapabilities
    {
        return new SearchCapabilities(fullText: true, typoTolerance: true);
    }

    public function query(DataScope $scope, SearchQuery $query): CandidatePage
    {
        if ($query->filters !== [] || $query->afterId !== null) {
            throw new InvalidArgumentException('This Meilisearch adapter supports only the declared common query subset.');
        }

        $result = $this->client->index($this->indexUid)->search($query->text, ['limit' => $query->limit + 1]);

        $hits = $result->getHits();
        $truncated = count($hits) > $query->limit;
        $visibleHits = array_slice($hits, 0, $query->limit);
        $candidates = array_map(
            static fn (array $hit): Candidate => new Candidate(new RecordRef(entity: $query->entity, id: self::candidateId($hit))),
            $visibleHits,
        );

        return new CandidatePage(candidates: $candidates, nextAfterId: null, truncated: $truncated);
    }

    /** @param array<string, mixed> $hit */
    private static function candidateId(array $hit): string
    {
        $id = $hit['id'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            throw new InvalidArgumentException('Meilisearch hit does not contain an approved opaque id.');
        }

        return (string) $id;
    }
}
