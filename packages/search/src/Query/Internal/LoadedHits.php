<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\SearchHit;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LoadedHits
{
    /** @param list<SearchHit> $hits
     * @param array<string,string> $revisions
     * @param list<Candidate> $candidates */
    public function __construct(public array $hits, public array $revisions, public array $candidates) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->hits, $this->revisions], JSON_THROW_ON_ERROR));
    }
}
