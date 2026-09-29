<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

use InvalidArgumentException;

final readonly class ApiVersion
{
    public function __construct(
        public string $major,
        public ApiVersionStatus $status,
        public ?string $supportedThrough,
    ) {
        if (preg_match('/^v[1-9][0-9]*$/', $major) !== 1) {
            throw new InvalidArgumentException('major: must be a public version line such as v1');
        }

        if ($supportedThrough !== null && preg_match('/^(?:v)?[0-9]+(?:\.[0-9xX]+)+$/', $supportedThrough) !== 1) {
            throw new InvalidArgumentException('supportedThrough: must be a release line, not a calendar date');
        }
    }

    /** @return array{major: string, status: string, supportedThrough: ?string} */
    public function toArray(): array
    {
        return [
            'major' => $this->major,
            'status' => $this->status->value,
            'supportedThrough' => $this->supportedThrough,
        ];
    }
}
