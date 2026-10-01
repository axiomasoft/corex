<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Data\NormalizedExtension;

/** Packages own compilation and normalization of their API declaration modes. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ManifestSectionCompiler
{
    public function mode(): string;

    public function phase(): int;

    /**
     * @param  list<Api>  $declarations
     * @param  list<Api>  $all
     */
    public function compile(array $declarations, array $all): void;

    /** @return list<NormalizedExtension> */
    public function normalize(ModuleManifest $manifest, Api $api, string $ownerVersion): array;
}
