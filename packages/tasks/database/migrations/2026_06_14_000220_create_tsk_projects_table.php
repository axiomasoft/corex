<?php

declare(strict_types=1);

use CoreX\Database\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsk_projects', function (Blueprint $table): void {
            IdColumns::primary($table);
            IdColumns::reference($table, 'process_id')->nullable();
            IdColumns::reference($table, 'company_id')->nullable();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('project');
            $table->string('status', 50)->default('active');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tsk_projects');
    }
};
