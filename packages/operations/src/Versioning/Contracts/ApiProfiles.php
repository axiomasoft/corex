<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Versioning\Data\ApiProfile;
use CoreX\Operations\Versioning\Data\EffectiveCapability;
use Packman\Modules\Data\Compiler\CompiledRegistry;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ApiProfiles
{
    public function make(CompiledRegistry $registry, ApiOptions $options): ApiProfile;

    /**
     * Live view. Activation changes this result and never the profile fingerprint.
     *
     * @param  array<string, bool>  $moduleAvailable
     * @param  list<string>|null  $knownIds  null skips the caller filter
     * @param  list<string>  $deniedIds
     * @return list<EffectiveCapability>
     */
    public function effective(ApiProfile $profile, array $moduleAvailable, ?array $knownIds = null, array $deniedIds = []): array;
}
