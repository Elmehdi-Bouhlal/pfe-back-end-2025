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
        Schema::create('reading_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['book', 'task'])->default('book');
            $table->enum('status', ['to_read', 'reading', 'completed'])->default('to_read');
            $table->enum('priority', ['low', 'medium', 'high'])->nullable();
            
            // Champs spécifiques aux livres
            $table->string('author')->nullable();
            $table->string('genre')->nullable();
            $table->integer('total_pages')->nullable();
            $table->integer('current_page')->default(0);
            $table->string('cover_image')->nullable();
            $table->integer('rating')->nullable(); // 1-5 étoiles
            $table->text('comment')->nullable();
            $table->string('isbn')->nullable();
            
            // Champs spécifiques aux tâches
            $table->date('due_date')->nullable();
            $table->integer('progress')->default(0); // 0-100%
            
            // Dates de suivi
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Ordre d'affichage dans la colonne
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_lists');
    }
};