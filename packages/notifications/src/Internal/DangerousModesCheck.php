<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Config\Data\CoreOptions;
use Override;
use Packman\Attributes\Internal;
use Packman\Config\Contracts\RuntimeConfigCheck;

/** @internal */
#[Internal]
final readonly class DangerousModesCheck implements RuntimeConfigCheck
{
    public function __construct(private NotificationsOptions $options, private CoreOptions $core) {}

    #[Override]
    public function violations(bool $production): array
    {
        if (! $production) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path): string => $path.': dangerous mode is not acknowledged for production',
            array_diff($this->options->dangerousModes(), $this->core->acknowledgedDangerousModes),
        ));
    }
}
