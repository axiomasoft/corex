<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Reference;

final class CompactReferenceProvider extends AbstractFixtureReferenceProvider
{
    public function __construct(
        string $fixturePath,
        FixtureReferenceNormalizer $normalizer,
        ReferenceCursorCodec $cursors,
    ) {
        parent::__construct(
            provider: 'compact',
            fixturePath: $fixturePath,
            normalizer: $normalizer,
            cursors: $cursors,
        );
    }
}
