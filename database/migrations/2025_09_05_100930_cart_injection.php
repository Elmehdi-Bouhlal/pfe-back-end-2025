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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamp('added_at')->nullable();
            $table->timestamps();
            
            // Prevent duplicate entries (same user + book)
            $table->unique(['user_id', 'book_id']);
            
            // Indexes for better performance
            $table->index(['user_id', 'created_at']);
            $table->index(['book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};