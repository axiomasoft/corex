<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface CandidateDriver
{
    /** Local capability metadata, independent of backend I/O. */
    public function capabilities(): DriverCapabilities;

    /** Apply the complete corpus before matching and ordering. Break ties by RecordRef ID.
     * Return the backend's actual document ID and namespace evidence for each row.
     */
    public function candidates(CandidateRequest $request): CandidatePage;
}
