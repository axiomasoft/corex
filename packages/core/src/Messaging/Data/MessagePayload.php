<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Messaging\Exceptions\InvalidMessage;
use Packman\Attributes\Api;
use stdClass;

/**
 * Канонизованный payload сообщения: ограниченная карта объектов из null, bool, int,
 * string и вложенных таких же карт и списков.
 *
 * Форма и пределы повторяют существующий прецедент дерева
 * SqlWakeupScheduler::canonical() — восемь уровней вложенности, 256 ключей на уровень,
 * 64 KiB в сериализованном виде, рекурсивная сортировка ключей. Второе правило на тот
 * же вопрос в одном репозитории было бы дефектом, а не гибкостью.
 *
 * Инвариант проверяется конструктором, а не типом: «рекурсивно ограниченная карта
 * скаляров» статическим типом PHP/PHPStan не выражается, поэтому MessageEnvelope
 * хранит этот объект, а не array<string, mixed>.
 */
/**
 * @phpstan-type JsonLevel0 null|bool|int|string
 * @phpstan-type JsonLevel1 null|bool|int|string|array<array-key, JsonLevel0>
 * @phpstan-type JsonLevel2 null|bool|int|string|array<array-key, JsonLevel1>
 * @phpstan-type JsonLevel3 null|bool|int|string|array<array-key, JsonLevel2>
 * @phpstan-type JsonLevel4 null|bool|int|string|array<array-key, JsonLevel3>
 * @phpstan-type JsonLevel5 null|bool|int|string|array<array-key, JsonLevel4>
 * @phpstan-type JsonLevel6 null|bool|int|string|array<array-key, JsonLevel5>
 * @phpstan-type JsonLevel7 null|bool|int|string|array<array-key, JsonLevel6>
 * @phpstan-type MessageMap array<string, JsonLevel7>
 */
#[Api(since: '1.0')]
final readonly class MessagePayload
{
    public const int MAX_DEPTH = 8;

    public const int MAX_KEYS = 256;

    public const int MAX_BYTES = 65_536;

    /** @var array<string, JsonLevel7> */
    public array $values;

    private string $wire;

    /** @param MessageMap|string $values A bounded PHP map or an encoded JSON object. */
    public function __construct(array|string $values)
    {
        if (is_string($values)) {
            $decoded = json_decode($values, false, 512, JSON_THROW_ON_ERROR);

            if (! $decoded instanceof stdClass) {
                throw InvalidMessage::payloadShape('must be a bounded object map, not a list or scalar');
            }
            $tree = self::canonicalValue($decoded, 0, true);
        } else {
            $tree = self::canonicalValue($values, 0, false);
        }
        $this->wire = json_encode($tree, JSON_THROW_ON_ERROR);

        if (strlen($this->wire) > self::MAX_BYTES) {
            throw InvalidMessage::payloadShape('exceeds 64KiB');
        }
        /** @var MessageMap $canonical The validated tree contains only bounded scalar maps and lists. */
        $canonical = json_decode($this->wire, true, flags: JSON_THROW_ON_ERROR);
        $this->values = $canonical;
    }

    public static function fromJson(string $json): self
    {
        return new self($json);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, JsonLevel7> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function encode(): string
    {
        return $this->wire;
    }

    private static function canonicalValue(mixed $value, int $depth, bool $fromJson): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidMessage::payloadShape('nesting exceeds eight levels');
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        $map = $fromJson && $value instanceof stdClass;

        if ($map) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            throw InvalidMessage::payloadShape('contains an unsupported value');
        }

        if (count($value) > self::MAX_KEYS) {
            throw InvalidMessage::payloadShape('contains too many values');
        }

        if ($depth === 0 && ! $map && $value !== [] && array_is_list($value)) {
            throw InvalidMessage::payloadShape('must be a bounded object map, not a list');
        }

        if ($depth > 0 && ! $map && array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::canonicalValue(value: $item, depth: $depth + 1, fromJson: $fromJson),
                $value,
            );
        }

        $canonical = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('contains an unsupported key');
            }

            $canonical[$key] = self::canonicalValue(value: $item, depth: $depth + 1, fromJson: $fromJson);
        }

        ksort($canonical);

        return (object) $canonical;
    }
}
