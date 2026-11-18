<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('tsk_task_watchers', function (Blueprint $table): void {
            $table->uuid('task_id');
            $table->uuid('user_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['task_id', 'user_id'], 'tsk_task_watchers_pk');

            $table->foreign(['task_id'], 'tsk_task_watchers_task_id_fk')->references(['id'])->on('tsk_tasks')->onDelete('cascade')->onUpdate('no action');
            $table->foreign(['user_id'], 'tsk_task_watchers_user_id_fk')->references(['id'])->on('users')->onDelete('cascade')->onUpdate('no action');
        });
    }

    public function down(): void
    {
        foreach (['tsk_timers', 'tsk_time_logs', 'tsk_task_watchers', 'tsk_task_targets', 'tsk_tasks', 'tsk_sprints', 'tsk_stages', 'tsk_projects'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    "CoreX Tasks cannot roll back a non-empty canonical table [{$table}]. Remove or migrate its data explicitly first.",
                );
            }
        }

        $this->drop('tsk_task_watchers');
    }
};
