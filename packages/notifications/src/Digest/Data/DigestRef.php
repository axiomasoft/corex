<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Data;

use CoreX\Attributes\Api;
use CoreX\Notifications\Delivery\Data\RecipientReference;

#[Api(since: '1.0')]
final readonly class DigestRef
{
    public function __construct(public string $id, public RecipientReference $recipient, public DigestWindow $window, public int $batchSequence) {}
}
