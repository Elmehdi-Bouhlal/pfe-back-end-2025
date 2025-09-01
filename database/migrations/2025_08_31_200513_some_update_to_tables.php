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
        Schema::table('books', function (Blueprint $table) {
            // Ajouter user_id si elle n'existe pas
            if (!Schema::hasColumn('books', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade')->after('id');
            }
            
            // Ajouter les colonnes manquantes si elles n'existent pas
            if (!Schema::hasColumn('books', 'is_negotiable')) {
                $table->boolean('is_negotiable')->default(false)->after('is_available');
            }
            
            if (!Schema::hasColumn('books', 'seller_notes')) {
                $table->text('seller_notes')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('books', 'file_path')) {
                $table->string('file_path')->nullable()->after('seller_notes');
            }
            
            if (!Schema::hasColumn('books', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('file_path');
            }
            
            if (!Schema::hasColumn('books', 'file_format')) {
                $table->string('file_format')->nullable()->after('file_size');
            }
            
            if (!Schema::hasColumn('books', 'download_count')) {
                $table->integer('download_count')->default(0)->after('file_format');
            }
            
            if (!Schema::hasColumn('books', 'view_count')) {
                $table->integer('view_count')->default(0)->after('download_count');
            }
            
            if (!Schema::hasColumn('books', 'like_count')) {
                $table->integer('like_count')->default(0)->after('view_count');
            }
            
            if (!Schema::hasColumn('books', 'share_count')) {
                $table->integer('share_count')->default(0)->after('like_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'is_negotiable',
                'seller_notes',
                'file_path',
                'file_size',
                'file_format',
                'download_count',
                'view_count',
                'like_count',
                'share_count'
            ]);
        });
    }
};