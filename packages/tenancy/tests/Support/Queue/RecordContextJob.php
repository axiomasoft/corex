<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Queue;

use CoreX\Context\Contracts\TenancyManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/** Records, per run, the context the job really executed in (account, workspace, physical database). */
final class RecordContextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var list<array{account: ?string, workspace: ?string, database: string, tag: string}> */
    public static array $runs = [];

    public function __construct(private readonly string $tag = '', private readonly bool $fail = false) {}

    public function handle(TenancyManager $manager, DatabaseManager $databases): void
    {
        $context = $manager->context();
        self::$runs[] = [
            'account' => $context?->account->id,
            'workspace' => $context?->workspace?->slug,
            'database' => $databases->connection()->getDatabaseName(),
            'tag' => $this->tag,
        ];

        if ($this->fail) {
            throw new RuntimeException('job failed: '.$this->tag);
        }
    }
}
