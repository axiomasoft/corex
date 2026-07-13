<?php

declare(strict_types=1);

use Flex\Modules\Contracts\RecordsRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sba_notes', function (Blueprint $table): void {
            $table->id();
            $table->text('body');
        });

        app(RecordsRegistrar::class)->claim('corex/stub-base', 'table', 'sba_notes');
    }

    public function down(): void
    {
        Schema::dropIfExists('sba_notes');
    }
};
