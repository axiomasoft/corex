<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corex_task_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_key', 128)->unique();
            $table->char('payload_hash', 64);
            $table->string('kind', 32);
            $table->json('result_ref');
            $table->char('restore_epoch', 64);
            $table->timestampTz('created_at')->useCurrent();
        });

        $this->addDatabaseConstraints();
    }

    public function down(): void
    {
        if (Schema::hasTable('corex_task_operations') && DB::table('corex_task_operations')->exists()) {
            throw new LogicException('Task operation receipts require an approved retention receipt before removal.');
        }

        Schema::dropIfExists('corex_task_operations');
    }

    private function addDatabaseConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_payload_hash_chk CHECK (payload_hash ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_restore_epoch_chk CHECK (restore_epoch ~ '^[a-f0-9]{64}$')");
            DB::statement("ALTER TABLE corex_task_operations ADD CONSTRAINT corex_task_operations_kind_chk CHECK (kind IN ('createProject','createSprint','recordTime','materialize','completionCapture'))");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER corex_task_operations_payload_hash_chk BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN length(NEW.payload_hash) = 64 AND NEW.payload_hash NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid payload_hash') END; END");
            DB::statement("CREATE TRIGGER corex_task_operations_restore_epoch_chk BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN length(NEW.restore_epoch) = 64 AND NEW.restore_epoch NOT GLOB '*[^a-f0-9]*' THEN 0 ELSE RAISE(ABORT, 'invalid restore_epoch') END; END");
            DB::statement("CREATE TRIGGER corex_task_operations_kind_chk BEFORE INSERT ON corex_task_operations BEGIN SELECT CASE WHEN NEW.kind IN ('createProject','createSprint','recordTime','materialize','completionCapture') THEN 0 ELSE RAISE(ABORT, 'invalid kind') END; END");
        }
    }
};
