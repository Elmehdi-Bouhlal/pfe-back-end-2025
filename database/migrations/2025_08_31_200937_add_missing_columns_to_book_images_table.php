<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('book_images')) {
        Schema::table('book_images', function (Blueprint $table) {
            // Ajouter les colonnes manquantes de votre modèle existant
            if (!Schema::hasColumn('book_images', 'image_name')) {
                $table->string('image_name')->after('filename');
            }
            
            if (!Schema::hasColumn('book_images', 'image_size')) {
                $table->integer('image_size')->nullable()->after('image_name');
            }
            
            if (!Schema::hasColumn('book_images', 'image_type')) {
                $table->string('image_type')->nullable()->after('image_size');
            }
        });
    }
    }

    public function down(): void
    {
        Schema::table('book_images', function (Blueprint $table) {
            $table->dropColumn(['image_name', 'image_size', 'image_type']);
        });
    }
};