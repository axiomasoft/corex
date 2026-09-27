<?php

declare(strict_types=1);

namespace CoreX\Messaging\Data;

use CoreX\Exceptions\InvalidMessage;

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
final readonly class MessagePayload
{
    public const int MAX_DEPTH = 8;

    public const int MAX_KEYS = 256;

    public const int MAX_BYTES = 65_536;

    /** @var array<string, mixed> */
    public array $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        $this->values = self::canonical($values);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, mixed> */
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
