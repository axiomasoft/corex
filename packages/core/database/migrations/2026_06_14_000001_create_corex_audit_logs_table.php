<?php

declare(strict_types=1);

use CoreX\Support\Config;
use CoreX\Support\Schema\IdColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Config::auditLogsTable(), function (Blueprint $table): void {
            IdColumns::primary($table);
            $table->string('auditable_type');
            IdColumns::reference($table, 'auditable_id');
            $table->string('event', 32);
            $table->json('changes')->nullable();
            $table->string('actor_type')->nullable();
            IdColumns::reference($table, 'actor_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::auditLogsTable());
    }
};
