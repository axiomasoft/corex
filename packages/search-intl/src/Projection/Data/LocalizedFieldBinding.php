<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Projection\Data;

use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LocalizedFieldBinding
{
    /** @param list<FieldUse> $uses */
    public function __construct(
        public string $fieldId,
        public string $declaredField,
        public string $schemaHash,
        public array $uses = [FieldUse::Read, FieldUse::Search],
    ) {
        new FieldSelection([$fieldId]);
    }

    /** Accepts a canonical tag from Intl's locale resolution. */
    public function shapeId(string $canonicalLocale): string
    {
        if (strlen($canonicalLocale) > 128 || preg_match('/\A[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\z/D', $canonicalLocale) !== 1) {
            throw new InvalidArgumentException('Expected a canonical Intl locale tag.');
        }
        $id = "localized:{$this->fieldId}:{$canonicalLocale}";

        return strlen($id) <= 128 ? $id : 'localized::v1:'.hash('sha256', $id);
    }
}
