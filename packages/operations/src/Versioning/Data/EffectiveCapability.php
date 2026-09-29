<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

/** Live caller view. It is not part of the code-profile fingerprint. */
final readonly class EffectiveCapability
{
    /**
     * @param  array<string, int|string>  $limits
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public ?string $reason,
        public array $limits,
    ) {}

    /** @return array{id: string, enabled: bool, reason: ?string, limits: array<string, int|string>} */
    public function publicMap(): array
    {
        return [
            'enabled' => $this->enabled,
            'id' => $this->id,
            'limits' => $this->limits,
            'reason' => $this->reason,
        ];
    }
}
