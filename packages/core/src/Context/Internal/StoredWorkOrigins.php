<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use Illuminate\Contracts\Encryption\Encrypter;
use Packman\Attributes\Internal;
use Packman\Foundation\ApplicationLocator;

#[Internal]
final readonly class StoredWorkOrigins implements StoredWorkIssuer
{
    public function __construct(private CurrentWorkScope $current, private DataScopeCodec $codec) {}

    public function issue(DataScope $scope, string $owner, string $reference): StoredWorkProof
    {
        $this->current->assert($scope);

        return new StoredWorkProof($owner, $reference, $this->seal($scope, $owner, $reference));
    }

    public function verify(DataScope $scope, StoredWorkProof $proof): void
    {
        if (! hash_equals($this->seal($scope, $proof->owner, $proof->reference), $proof->seal)) {
            throw new ScopeContextMismatch('Unverified stored work provenance.');
        }
    }

    private function seal(DataScope $scope, string $owner, string $reference): string
    {
        $key = ApplicationLocator::current()->make(Encrypter::class)->getKey();

        return hash_hmac('sha256', json_encode(['corex.stored-work.v1', $owner, $reference, $this->codec->encode($scope)], JSON_THROW_ON_ERROR), $key);
    }
}
