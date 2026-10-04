<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Config\Data\CoreOptions;
use Override;

/** @internal */
final readonly class DangerousModesCheck implements RuntimeConfigCheck
{
    public function __construct(private WorkflowOptions $options, private CoreOptions $core) {}

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
