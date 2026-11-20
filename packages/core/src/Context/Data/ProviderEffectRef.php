<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Data\Data\ScopeIdentity;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProviderEffectRef
{
    public function __construct(
        public string $accountId,
        public string $accountIncarnation,
        public ?string $workspaceId,
        public string $owner,
        public string $providerNamespace,
        public string $effectKey,
    ) {
        ScopeIdentity::opaque($accountId);
        ScopeIdentity::opaque($accountIncarnation);

        if ($workspaceId !== null) {
            ScopeIdentity::opaque($workspaceId);
        }
        foreach ([$owner, $providerNamespace, $effectKey] as $value) {
            if ($value === '' || strlen($value) > 191 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new InvalidArgumentException('Invalid provider effect identity.');
            }
        }
    }

    public function identity(): string
    {
        return hash('sha256', json_encode(['corex.provider-effect.v2', $this->accountId, $this->accountIncarnation,
            $this->workspaceId, $this->owner, $this->providerNamespace, $this->effectKey], JSON_THROW_ON_ERROR));
    }
}
