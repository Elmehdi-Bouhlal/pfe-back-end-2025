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
        // Ajouter les champs manquants pour les livres numériques
        Schema::table('books', function (Blueprint $table) {
            // Champs spécifiques aux livres numériques
            $table->integer('pages')->nullable()->after('isbn');
            $table->integer('download_limit')->nullable()->after('pages');
            $table->text('sample_content')->nullable()->after('download_limit');
            
            // Rendre book_condition nullable pour les livres numériques
            $table->string('book_condition')->nullable()->change();
        });
        
        // Créer la table pour les fichiers numériques
        Schema::create('book_digital_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->enum('file_type', ['pdf', 'epub', 'mobi', 'cover'])->default('pdf');
            $table->string('file_path');
            $table->string('file_name');
            $table->bigInteger('file_size'); // en bytes
            $table->string('mime_type');
            $table->boolean('is_active')->default(true);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['book_id', 'file_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['pages', 'download_limit', 'sample_content']);
        });
        
        Schema::dropIfExists('book_digital_files');
    }
};