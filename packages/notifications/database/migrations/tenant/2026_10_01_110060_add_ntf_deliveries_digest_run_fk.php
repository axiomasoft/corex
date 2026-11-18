<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('ntf_deliveries', function (Blueprint $table): void {
            $table->foreign('digest_run_id', 'ntf_deliveries_digest_run_id_fk')->references('id')->on('ntf_digest_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            Schema::table('ntf_deliveries', function (Blueprint $table): void {
                $table->dropForeign('ntf_deliveries_digest_run_id_fk');
            });
        }
    }
};
