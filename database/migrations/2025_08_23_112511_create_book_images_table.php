<?php

// =====================================================
// FICHIER: 2024_01_01_000003_create_book_images_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade')->comment('ID du livre');
            $table->string('image_path', 500)->comment('Chemin vers l\'image');
            $table->string('image_name', 255)->comment('Nom original du fichier');
            $table->bigInteger('image_size')->comment('Taille en bytes');
            $table->string('image_type', 20)->comment('Type MIME (image/jpeg, etc.)');
            $table->integer('sort_order')->default(0)->comment('Ordre d\'affichage (0 = image principale)');
            $table->boolean('is_primary')->default(false)->comment('Image principale');
            $table->string('alt_text', 255)->nullable()->comment('Texte alternatif');
            $table->timestamps();
            
            $table->index(['book_id']);
            $table->index(['is_primary']);
            $table->index(['sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_images');
    }
};

?>