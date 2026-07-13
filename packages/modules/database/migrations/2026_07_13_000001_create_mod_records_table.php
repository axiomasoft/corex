<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ownership registry the FSM purges by (B-10 §5.4) — no FK to mod_modules,
// module_name is a logical key (DB_SCHEMA.md §3.3 mod_records).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_records', function (Blueprint $table): void {
            $table->string('module_name', 190);
            $table->string('record_type', 40);
            $table->string('reference', 255);
            $table->jsonb('meta')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->primary(['module_name', 'record_type', 'reference']);
        });

        DB::statement('CREATE INDEX mod_records_module_ix ON mod_records (module_name, created_at DESC)');
        DB::statement("ALTER TABLE mod_records ADD CONSTRAINT mod_records_type_ck CHECK (record_type IN ('table','column','index','role','permission','menu','setting','seed','blueprint','feature_flag','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_records');
    }
};
