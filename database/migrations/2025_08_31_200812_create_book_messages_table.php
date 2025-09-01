<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('book_messages')) {
        Schema::create('book_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('book_conversations')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->enum('message_type', ['text', 'image', 'offer'])->default('text');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id']);
        });
    }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_messages');
    }
};