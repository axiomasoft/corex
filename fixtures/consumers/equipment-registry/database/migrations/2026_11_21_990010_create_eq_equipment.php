<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('eq_equipment', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 128);
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        $this->drop('eq_equipment');
    }
};
