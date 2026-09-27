<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Класс персональных данных в payload'е сообщения (MSG-007).
 *
 * Порядок значений — возрастание чувствительности: контракт объявляет максимально
 * допустимый класс, и сообщение с более высоким классом в него не принимается.
 */
enum PiiClass: string
{
    /** Данных, относящихся к человеку, нет вовсе. */
    case None = 'none';

    /** Только идентификаторы: связать с человеком можно лишь отдельно хранимыми данными. */
    case Pseudonymous = 'pseudonymous';

    /** Прямо идентифицирующие значения: имя, адрес, контакт, документ. */
    case Personal = 'personal';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Pseudonymous => 1,
            self::Personal => 2,
        };
    }

    public function permits(self $other): bool
    {
        return $other->rank() <= $this->rank();
    }
}
