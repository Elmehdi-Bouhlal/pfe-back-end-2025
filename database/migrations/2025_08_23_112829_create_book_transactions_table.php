<?php

// =====================================================
// FICHIER: 2024_01_01_000009_create_book_transactions_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('restrict');
            $table->foreignId('seller_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('restrict');
            
            // Détails de la transaction
            $table->decimal('agreed_price', 10, 2)->comment('Prix convenu');
            $table->decimal('original_price', 10, 2)->comment('Prix initial');
            $table->string('currency', 3)->default('MAD');
            
            // Statut et dates
            $table->enum('status', ['pending', 'confirmed', 'paid', 'delivered', 'completed', 'cancelled', 'disputed'])
                  ->default('pending');
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamp('completion_date')->nullable();
            
            // Détails de livraison (pour livres physiques)
            $table->enum('delivery_method', ['pickup', 'delivery', 'shipping'])->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('tracking_number', 100)->nullable();
            
            // Notes
            $table->text('seller_notes')->nullable();
            $table->text('buyer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['book_id']);
            $table->index(['seller_id']);
            $table->index(['buyer_id']);
            $table->index(['status']);
            $table->index(['transaction_date', 'status'], 'idx_transaction_date_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_transactions');
    }
};

?>
