<?php
// database/migrations/xxxx_xx_xx_create_payment_methods_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['paypal', 'cash_on_delivery', 'bank_card'])->default('cash_on_delivery');
            $table->string('provider_name')->nullable(); // PayPal, Visa, etc.
            $table->json('details')->nullable(); // Encrypted payment details
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
};