<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Tests\Golden\Support\GoldenSnapshot;
use PHPUnit\Framework\TestCase;

interface InheritedPort {}

interface DirectPort extends InheritedPort {}

interface ChildPort extends DirectPort {}

class Dependency implements InheritedPort {}

trait DependencyMembers
{
    public const string IMPORTED = 'upstream';

    public const string REDECLARED = 'same';

    public string $imported = 'upstream';

    public string $redeclared = 'same';

    public function external(): void {}
}

trait Wrapper
{
    use DependencyMembers;
}

final class SnapshotSubject extends Dependency implements DirectPort
{
    use Wrapper {
        external as protected renamed;
    }

    public const string REDECLARED = 'same';

    public string $redeclared = 'same';

    public function __construct(public readonly int $promoted) {}

    public function own(): void {}
}

final class GoldenSurfaceTest extends TestCase
{
    public function test_only_declarations_owned_by_the_snapshot_type_are_recorded(): void
    {
        $snapshot = GoldenSnapshot::phpSurface([SnapshotSubject::class, ChildPort::class]);
        $subject = $snapshot[SnapshotSubject::class];
        self::assertSame(Dependency::class, $subject['parent']);
        self::assertSame([DirectPort::class], $subject['interfaces']);
        self::assertSame([DirectPort::class], $snapshot[ChildPort::class]['interfaces']);
        self::assertSame([Wrapper::class], $subject['traits'] ?? []);
        self::assertSame(['external as protected renamed;'], $subject['trait_adaptations'] ?? []);
        self::assertSame(['REDECLARED'], array_keys($subject['constants']));
        self::assertSame(['promoted', 'redeclared'], array_keys($subject['properties']));
        self::assertSame(['__construct', 'own'], array_keys($subject['methods']));
    }
}
