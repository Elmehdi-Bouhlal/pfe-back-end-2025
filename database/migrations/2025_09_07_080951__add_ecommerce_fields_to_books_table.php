<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('price');
            $table->json('payment_methods')->nullable()->after('currency');
            $table->integer('shipping_delay_days')->nullable()->after('payment_methods');
            $table->json('shipping_cities')->nullable()->after('shipping_delay_days');
            $table->decimal('shipping_cost', 8, 2)->nullable()->after('shipping_cities');
            $table->boolean('free_shipping_above')->nullable()->after('shipping_cost');
            $table->decimal('free_shipping_threshold', 8, 2)->nullable()->after('free_shipping_above');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            //
        });
    }
};
