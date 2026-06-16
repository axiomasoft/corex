<?php

declare(strict_types=1);

use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_stages', function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::reference($table, 'tenant_id')->nullable()->index();
            IdColumns::foreign($table, 'process_id')->constrained('tsk_processes')->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('color', 7)->default('#6B7280');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_stages');
    }
};
