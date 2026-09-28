<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Modules\Compiler\NormalizedExtension;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\ModuleManifest;

/** Packages own compilation and normalization of their API declaration modes. */
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
