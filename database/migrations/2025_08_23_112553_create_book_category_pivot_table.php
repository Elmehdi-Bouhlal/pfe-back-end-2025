<?php

// =====================================================
// FICHIER: 2024_01_01_000004_create_book_category_pivot_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_category_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('book_categories')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['book_id', 'category_id'], 'unique_book_category');
            $table->index(['book_id']);
            $table->index(['category_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_category_pivot');
    }
};

?>