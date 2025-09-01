<?php

// =====================================================
// FICHIER: 2024_01_01_000007_create_book_conversations_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade')
                  ->comment('Acheteur potentiel');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade')
                  ->comment('Vendeur du livre');
            $table->enum('status', ['active', 'closed', 'blocked'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            $table->unique(['book_id', 'buyer_id', 'seller_id'], 'unique_book_buyer_seller');
            $table->index(['book_id']);
            $table->index(['buyer_id']);
            $table->index(['seller_id']);
            $table->index(['status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_conversations');
    }
};

?>