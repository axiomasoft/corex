<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\StoredWorkProof;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;

#[Internal]
final readonly class WakeupClaim
{
    public function __construct(
        public string $id,
        public string $token,
        public int $generation,
        public int $attempt,
        public string $scope,
        public string $digest,
        public string $seal,
        public string $workspace,
        public DateTimeImmutable $deadline,
    ) {}

    public function proof(): StoredWorkProof
    {
        return new StoredWorkProof(owner: WakeupInput::PROOF_OWNER, reference: WakeupInput::proofReference(id: $this->id, digest: $this->digest), seal: $this->seal);
    }

    public function operation(): OperationKey
    {
        return new OperationKey(id: 'wakeup:'.$this->id, attempt: $this->generation, effectKey: 'wakeup:'.$this->id, deadline: $this->deadline);
    }

    public function row(Connection $db): Builder
    {
        return $db->table('sys_wakeup_requests')->where('id', $this->id)
            ->where('generation', $this->generation)->where('claim_token', $this->token)
            ->where('request_digest', $this->digest)->where('scope_seal', $this->seal);
    }
}
