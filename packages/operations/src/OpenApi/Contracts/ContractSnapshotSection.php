<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Contracts;

/** A package-owned document in the installed-code contract snapshot. */
interface ContractSnapshotSection
{
    public function file(): string;

    /** @return array<string, mixed> */
    public function document(string $major): array;
}
