<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getColumnType('crm_activities', 'subject_id'), ['bpchar', 'char', 'varchar', 'string'], true)) {
            // Preserve existing ULID references while admitting UUID subjects on upgraded installs.
            Schema::table('crm_activities', static fn (Blueprint $table) => $table->string('subject_id', 36)->change());
        }
    }

    public function down(): void
    {
        // Narrowing would truncate references written after this migration.
    }
};
