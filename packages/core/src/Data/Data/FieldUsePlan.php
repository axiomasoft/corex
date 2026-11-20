<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use CoreX\Data\Enums\FieldUsePlanKind;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldUsePlan
{
    /** @param list<string> $recordIds Exact authorized corpus for a scoped predicate. */
    private function __construct(public FieldUsePlanKind $kind, public ?string $witness, public array $recordIds) {}

    public static function denied(): self
    {
        return new self(FieldUsePlanKind::Denied, null, []);
    }

    public static function unconditional(string $witness): self
    {
        self::checkWitness($witness);

        return new self(FieldUsePlanKind::Unconditional, $witness, []);
    }

    /** @param list<string> $recordIds */
    public static function scoped(string $witness, array $recordIds): self
    {
        self::checkWitness($witness);

        if (count($recordIds) > 1000) {
            throw new InvalidArgumentException('Invalid exact corpus.');
        }
        $position = 0;
        foreach ($recordIds as $index => $id) {
            if ($index !== $position++ || $id === '' || strlen($id) > 128) {
                throw new InvalidArgumentException('Invalid corpus record.');
            }
        }

        return new self(FieldUsePlanKind::ScopedPredicate, $witness, $recordIds);
    }

    private static function checkWitness(string $witness): void
    {
        if ($witness === '' || strlen($witness) > 191) {
            throw new InvalidArgumentException('A field-use grant requires its current policy witness.');
        }
    }
}
