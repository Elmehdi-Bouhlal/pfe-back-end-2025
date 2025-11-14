<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('reading_preferences')->nullable()->after('email_verified_at');
            $table->string('preferred_language')->default('french')->after('reading_preferences');
            $table->json('favorite_genres')->nullable()->after('preferred_language');
            $table->boolean('ai_assistance_enabled')->default(true)->after('favorite_genres');
            $table->enum('reading_theme', ['light', 'dark', 'sepia'])->default('light')->after('ai_assistance_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'reading_preferences',
                'preferred_language', 
                'favorite_genres',
                'ai_assistance_enabled',
                'reading_theme'
            ]);
        });
    }
};
