<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Data\IndexBatch;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class VerifiedIndexLookup implements IndexLookupAccess
{
    public function __construct(private DatabaseManager $database, private CurrentWorkScope $current,
        private ProviderEffectLedger $effects, private ProviderEffectRecovery $recovery) {}

    public function assert(IndexBatch $batch): void
    {
        $connection = $this->database->connection();

        try {
            $this->current->assert($batch->target->scope, $connection);
        } catch (ScopeContextMismatch $closed) {
            $status = $this->effects->inspect(Submission::effectFor($batch));

            if ($status === null || $status->ownerRef !== $batch->submissionId || $status->digest !== Submission::digest($batch)
                || get_object_vars($batch->target->scope) !== get_object_vars($status->ticket->scope)) {
                throw $closed;
            }
            $this->recovery->assertReceipt($status, $connection);
        }
    }
}
