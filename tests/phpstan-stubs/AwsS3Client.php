<?php

declare(strict_types=1);

namespace Aws\S3;

/** Minimal stub for phpstan-tests-lint when AWS SDK is not installed at repo root. */
final readonly class S3Client
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config) {}

    /** @param array<string, mixed> $args @return array{Body: mixed} */
    public function getObject(array $args): array
    {
        return ['Body' => ''];
    }
}
