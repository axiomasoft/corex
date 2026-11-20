<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Contracts;

use CoreX\Search\Index\Data\IndexTarget;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Data\SecretRef;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface MeilisearchCredentialProvider
{
    /** Returns a provider-enforced credential for this exact UID and access mode. */
    public function forTarget(IndexTarget $target, MeilisearchAccess $access): SecretRef;
}
