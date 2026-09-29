<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Data;

use CoreX\Tasks\Time\Models\TimeLog;

final readonly class TimeLogReference
{
    public const string TYPE = 'tsk.time-log';

    public function __construct(
        public string $id,
        public string $type,
        public string $display,
    ) {}

    public static function fromTimeLog(TimeLog $timeLog): self
    {
        return new self(
            id: (string) $timeLog->getKey(),
            type: self::TYPE,
            display: sprintf('%dm on %s', $timeLog->minutes, $timeLog->spent_on->format('Y-m-d')),
        );
    }

    /** @return array{id: string, type: string, display: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'display' => $this->display,
        ];
    }

    /** @param array{id: string, type: string, display: string} $stored */
    public static function fromStored(array $stored): self
    {
        return new self(
            id: $stored['id'],
            type: $stored['type'],
            display: $stored['display'],
        );
    }
}
