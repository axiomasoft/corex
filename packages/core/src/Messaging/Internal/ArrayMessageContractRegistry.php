<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use Override;

/**
 * Неизменяемый реестр контрактов, собираемый в composition root.
 *
 * Дубликат идентичности — ошибка построения, а не молчаливое переопределение: это тот
 * же инвариант, что EXT-006 требует от любого реестра. Полная модель реестров
 * (collecting → compiled → frozen, отпечаток) вводится в P11; здесь достаточно
 * неизменяемости после сборки.
 */
final readonly class ArrayMessageContractRegistry implements MessageContractRegistry
{
    /** @var array<string, MessageContract> */
    private array $contracts;

    /** @param list<MessageContract> $contracts */
    public function __construct(array $contracts)
    {
        $indexed = [];

        foreach ($contracts as $contract) {
            if (isset($indexed[$contract->id()])) {
                throw UnknownMessageContract::duplicate($contract->id());
            }

            $indexed[$contract->id()] = $contract;
        }

        ksort($indexed);

        $this->contracts = $indexed;
    }

    #[Override]
    public function has(string $name, int $version): bool
    {
        return isset($this->contracts["{$name}.v{$version}"]);
    }

    #[Override]
    public function get(string $name, int $version): MessageContract
    {
        return $this->contracts["{$name}.v{$version}"]
            ?? throw UnknownMessageContract::notDeclared(name: $name, version: $version);
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->contracts);
    }
}
