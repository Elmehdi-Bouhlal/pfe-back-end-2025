<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('interaction_type', ['summarize', 'explain', 'chat', 'recommend', 'study_notes']);
            $table->text('user_input');
            $table->text('ai_response');
            $table->integer('page_number')->nullable();
            $table->json('context_data')->nullable(); // for storing additional context
            $table->integer('response_time_ms')->nullable(); // AI response time
            $table->float('user_rating')->nullable(); // user feedback on AI response (1-5)
            $table->timestamps();

            $table->index(['user_id', 'book_id']);
            $table->index(['interaction_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};

