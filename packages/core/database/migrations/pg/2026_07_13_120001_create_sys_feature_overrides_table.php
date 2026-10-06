<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Database\Laravel\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Executed from the package schema set on its declared connection.
return new class extends Migration
{
    public function up(): void
    {
        $table = app(CoreOptions::class)->featureOverridesTable;

        Schema::create($table, function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::foreign($table, 'flag_id')->constrained(app(CoreOptions::class)->featureFlagsTable)->cascadeOnDelete();
            $table->string('scope_type', 16);
            IdColumns::reference($table, 'scope_id');
            $table->boolean('is_enabled');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();
        });

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_fo_scope_ck CHECK (scope_type IN ('workspace','user','department'))");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT sys_fo_uq UNIQUE (flag_id, scope_type, scope_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists(app(CoreOptions::class)->featureOverridesTable);
    }
};
