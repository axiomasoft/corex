<?php

declare(strict_types=1);

namespace CoreX\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;

/** @internal Ownership is established by the Laravel composition boundary from Composer metadata. */
final readonly class ProblemContribution
{
    /** @param list<string> $sourceRoots */
    public function __construct(public string $owner, public ProblemDefinitionProvider $provider, public ?string $package = null, public array $sourceRoots = []) {}
}
