<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class DigestRef
{
    public function __construct(public string $id, public RecipientReference $recipient, public DigestWindow $window, public int $batchSequence) {}
}
