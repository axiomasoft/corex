<?php

declare(strict_types=1);

namespace CoreX\Search\Internal;

use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use Packman\Attributes\Internal;

#[Internal]
final class UnavailableSearchProvider implements CandidateDriver
{
    public function capabilities(): DriverCapabilities
    {
        return new DriverCapabilities;
    }

    public function candidates(CandidateRequest $request): CandidatePage
    {
        throw SearchFeatureUnavailable::unsupported();
    }
}
