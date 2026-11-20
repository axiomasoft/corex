<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Search\Projection\Data\ProjectionInput;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class CandidatePage
{
    /** @param list<Candidate> $candidates */
    public function __construct(public array $candidates, public ?string $nextPosition)
    {
        ProjectionInput::objects($candidates, Candidate::class);

        if ($nextPosition !== null && ($nextPosition === '' || strlen($nextPosition) > 8192)) {
            throw new InvalidArgumentException('Invalid backend search position.');
        }
    }
}
