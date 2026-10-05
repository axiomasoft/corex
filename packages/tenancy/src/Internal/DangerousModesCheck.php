<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Internal;

use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Config\Data\CoreOptions;
use CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Override;

/** @internal */
final readonly class DangerousModesCheck implements RuntimeConfigCheck
{
    public function __construct(private TenancyOptions $options, private CoreOptions $core) {}

    #[Override]
    public function violations(bool $production): array
    {
        $path = 'corex-tenancy.impersonation.profile';

        if (! $production || $this->options->impersonation->profile !== ImpersonationProfile::Dev
            || in_array($path, $this->core->acknowledgedDangerousModes, true)) {
            return [];
        }

        return [$path.': dangerous mode is not acknowledged for production'];
    }
}
