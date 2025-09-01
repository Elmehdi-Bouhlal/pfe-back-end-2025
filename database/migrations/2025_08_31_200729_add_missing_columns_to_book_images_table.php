<?php

// Migration pour book_conversations
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('book_conversations')) {
            Schema::create('book_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
                $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', ['active', 'closed', 'archived'])->default('active');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                
                $table->index(['buyer_id']);
                $table->index(['seller_id']);
                $table->index(['book_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_conversations');
    }
};
