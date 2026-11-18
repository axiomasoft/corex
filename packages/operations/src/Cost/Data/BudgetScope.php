<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class BudgetScope
{
    public const string AnonymousActor = '__anonymous_actor__';

    public const string AnonymousClient = '__anonymous_client__';

    public string $clientId;

    public string $actorId;

    public function __construct(
        public string $installationId,
        public string $accountId,
        ?string $clientId,
        ?string $actorId,
    ) {
        $this->assertIdentifier(value: $installationId, label: 'installation');
        $this->assertIdentifier(value: $accountId, label: 'account');

        $this->clientId = $this->optionalIdentifier(value: $clientId, anonymous: self::AnonymousClient, label: 'client');
        $this->actorId = $this->optionalIdentifier(value: $actorId, anonymous: self::AnonymousActor, label: 'actor');
    }

    private function assertIdentifier(string $value, string $label): void
    {
        $length = strlen($value);

        if ($length < 1 || $length > 255 || str_contains($value, "\0")) {
            throw new InvalidArgumentException("Budget {$label} identifier must contain 1-255 safe bytes.");
        }
    }

    private function optionalIdentifier(?string $value, string $anonymous, string $label): string
    {
        if ($value === null || $value === '') {
            return $anonymous;
        }

        $this->assertIdentifier(value: $value, label: $label);

        if ($value === $anonymous) {
            throw new InvalidArgumentException("Budget {$label} identifier uses a reserved anonymous sentinel.");
        }

        return $value;
    }
}
