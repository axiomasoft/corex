<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Enums\IdStrategy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Config-driven primary key: UUIDv7 (default, per ADR-003), UUID, ULID, or
 * auto-increment int — per config('corex.ids.strategy'). A single seam so a
 * host app can line CoreX models up with its own key type without forking
 * the package (mirrors AzGuard's configurable morph key). Pair with
 * CoreX\Database\Laravel\IdColumns in migrations so the column type matches.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
trait HasConfiguredId
{
    public static function bootHasConfiguredId(): void
    {
        static::creating(function (Model $model): void {
            if (app(CoreOptions::class)->idStrategy === IdStrategy::Int) {
                return;
            }

            foreach ($model->uniqueIds() as $column) {
                if (empty($model->getAttribute($column))) {
                    $model->setAttribute($column, $model->newUniqueId());
                }
            }
        });
    }

    /**
     * Columns that receive a generated id. Empty for the int strategy
     * (the database auto-increments).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return app(CoreOptions::class)->idStrategy === IdStrategy::Int ? [] : [$this->getKeyName()];
    }

    public function newUniqueId(): string
    {
        return match (app(CoreOptions::class)->idStrategy) {
            IdStrategy::Uuid7 => (string) Str::uuid7(),
            IdStrategy::Uuid => (string) Str::orderedUuid(),
            default => (string) Str::ulid(),
        };
    }

    public function getKeyType(): string
    {
        return app(CoreOptions::class)->idStrategy === IdStrategy::Int ? 'int' : 'string';
    }

    public function getIncrementing(): bool
    {
        return app(CoreOptions::class)->idStrategy === IdStrategy::Int;
    }
}
