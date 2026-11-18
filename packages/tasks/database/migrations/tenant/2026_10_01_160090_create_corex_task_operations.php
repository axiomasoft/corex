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
        $this->create('corex_task_operations', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'corex_task_operations_pk');
            $table->string('request_key', 128);
            $table->char('payload_hash', 64);
            $table->string('kind', 32);
            $table->json('result_ref');
            $table->char('restore_epoch', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['request_key'], 'corex_task_operations_request_key_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_kind_ck CHECK (kind IN (\'createProject\',\'createSprint\',\'recordTime\',\'materialize\',\'completionCapture\'))');
            DB::statement('ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_payload_hash_ck CHECK (payload_hash ~ \'^[a-f0-9]{64}$\')');

            DB::statement('ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_restore_epoch_ck CHECK (restore_epoch ~ \'^[a-f0-9]{64}$\')');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE TRIGGER corex_task_operations_kind_ck BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN NEW.kind IN (\'createProject\',\'createSprint\',\'recordTime\',\'materialize\',\'completionCapture\') THEN 0 ELSE RAISE(ABORT, \'invalid kind\') END; END');
            DB::statement('CREATE TRIGGER corex_task_operations_payload_hash_ck BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN length(NEW.payload_hash) = 64 AND NEW.payload_hash NOT GLOB \'*[^a-f0-9]*\' THEN 0 ELSE RAISE(ABORT, \'invalid payload_hash\') END; END');

            DB::statement('CREATE TRIGGER corex_task_operations_restore_epoch_ck BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB \'*[^a-f0-9]*\' THEN 0 ELSE RAISE(ABORT, \'invalid restore_epoch\') END; END');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('corex_task_operations') && DB::table('corex_task_operations')->exists()) {
            throw new LogicException('Task operation receipts require an approved retention receipt before removal.');
        }

        $this->drop('corex_task_operations');
    }
};
