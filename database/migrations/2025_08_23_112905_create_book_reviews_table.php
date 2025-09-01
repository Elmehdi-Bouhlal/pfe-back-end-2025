<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained('book_transactions')->onDelete('set null')
                  ->comment('Lié à une transaction');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade')
                  ->comment('Utilisateur qui donne l\'avis');
            $table->foreignId('reviewed_user_id')->constrained('users')->onDelete('cascade')
                  ->comment('Utilisateur évalué (vendeur/acheteur)');
            
            // Évaluation
            $table->integer('rating')->comment('Note de 1 à 5');
            $table->text('review_text')->nullable()->comment('Commentaire textuel');
            $table->enum('review_type', ['seller', 'buyer', 'book'])->default('seller')
                  ->comment('Type d\'évaluation');
            
            // Statut
            $table->boolean('is_verified')->default(false)->comment('Avis vérifié (achat confirmé)');
            $table->boolean('is_approved')->default(true)->comment('Avis approuvé par modération');
            
            $table->timestamps();
            
            $table->index(['book_id']);
            $table->index(['transaction_id']);
            $table->index(['reviewer_id']);
            $table->index(['reviewed_user_id']);
            $table->index(['rating']);
            $table->index(['is_verified']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_reviews');
    }
};
