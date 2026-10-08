<?php
declare(strict_types=1);
namespace CoreX\InfectionSpike;
final class Controls
{
    public static function escaped(): bool { return true; }
    public static function uncovered(): bool { return false; }
    public static function timeout(): bool { return false; }
    public static function error(): bool { return false; }
}
