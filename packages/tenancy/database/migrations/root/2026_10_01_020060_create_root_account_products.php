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
        Schema::create('root_account_products', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->string('product', 32);
            $table->timestampTz('activated_at', precision: 6)->useCurrent();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['account_id', 'product'], 'root_account_products_pk');
            $table->foreign('account_id', 'root_account_products_account_id_fk')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        Schema::getConnection()->statement('ALTER TABLE root_account_products RENAME CONSTRAINT root_account_products_pkey TO root_account_products_pk');

        DB::statement("ALTER TABLE root_account_products ADD CONSTRAINT root_account_products_product_ck CHECK (product ~ '^[a-z0-9]([a-z0-9_-]*[a-z0-9])?\$')");

        DB::statement("COMMENT ON TABLE root_account_products IS 'Активированные продукты аккаунта. product — слаг продукта прикладного уровня (формат: строчные латиница/цифры/-/_, непустой, ≤32); допустимый НАБОР продуктов задаётся приложением-потребителем, схема ядра его не перечисляет'");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_account_products');
    }
};
