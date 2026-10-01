<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ModuleExplanation
{
    /**
     * @param  array{}|array{requires: list<array{kind: string, package: string, constraint: string}>, editions: array<string, list<string>>, components: list<class-string>, compatibilityPassport: string, installedPackagePresent: bool}  $declaredConstraints
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $schemaVersion,
        public string $module,
        public string $status,
        public array $declaredConstraints,
        public array $reasons,
        public bool $hasTenantVerdict,
    ) {}
}
