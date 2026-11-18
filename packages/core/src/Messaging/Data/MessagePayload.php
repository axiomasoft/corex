<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Messaging\Exceptions\InvalidMessage;
use Packman\Attributes\Api;

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

    /** @param array<string, JsonLevel7> $values */
    public function __construct(array $values)
    {
        /** @var array<string, JsonLevel7> $canonical Validated by the bounded scalar-tree validator. */
        $canonical = self::canonical($values);
        $this->values = $canonical;
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw InvalidMessage::payloadShape('must be a bounded object map, not a scalar');
        }
        /** @var MessageMap $canonical Validated by the same bounded scalar-tree validator as construction. */
        $canonical = self::canonical($decoded);

        return new self($canonical);
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
        return json_encode($this->values, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function canonical(array $values): array
    {
        if ($values !== [] && array_is_list($values)) {
            throw InvalidMessage::payloadShape('must be a bounded object map, not a list');
        }

        if (count($values) > self::MAX_KEYS) {
            throw InvalidMessage::payloadShape('contains too many values');
        }

        $canonical = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('contains an unsupported key');
            }

            $canonical[$key] = self::canonicalValue(value: $value, depth: 1);
        }

        ksort($canonical);

        if (strlen(json_encode($canonical, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) {
            throw InvalidMessage::payloadShape('exceeds 64KiB');
        }

        return $canonical;
    }

    private static function canonicalValue(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidMessage::payloadShape('nesting exceeds eight levels');
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw InvalidMessage::payloadShape('contains an unsupported value');
        }

        if (count($value) > self::MAX_KEYS) {
            throw InvalidMessage::payloadShape('contains too many values');
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::canonicalValue(value: $item, depth: $depth + 1),
                $value,
            );
        }

        $canonical = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                throw InvalidMessage::payloadShape('contains an unsupported key');
            }

            $canonical[$key] = self::canonicalValue(value: $item, depth: $depth + 1);
        }

        ksort($canonical);

        return $canonical;
    }
}
