<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Context\Exceptions\ScopeContextMismatch;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class StoredWorkProof
{
    public function __construct(public string $owner, public string $reference, public string $seal)
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,95}\z/D', $owner) !== 1 || $reference === '' || strlen($reference) > 191
            || preg_match('/[\x00-\x1f\x7f]/', $reference) === 1 || preg_match('/\A[a-f0-9]{64}\z/D', $seal) !== 1) {
            throw new ScopeContextMismatch('Invalid stored work provenance.');
        }
    }
}
