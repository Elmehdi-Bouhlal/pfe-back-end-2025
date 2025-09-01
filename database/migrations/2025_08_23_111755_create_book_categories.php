<?php

// =====================================================
// FICHIER: 2024_01_01_000001_create_book_categories_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Nom de la catégorie');
            $table->string('slug', 100)->unique()->comment('URL-friendly name');
            $table->text('description')->nullable()->comment('Description de la catégorie');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Catégorie parente');
            $table->integer('sort_order')->default(0)->comment('Ordre d\'affichage');
            $table->boolean('is_active')->default(true)->comment('Catégorie active');
            $table->timestamps();

            $table->index(['parent_id']);
            $table->index(['slug']);
            $table->index(['is_active']);
            $table->foreign('parent_id')->references('id')->on('book_categories')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_categories');
    }
};

?>