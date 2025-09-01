<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('book_categories')) {
        Schema::table('book_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('book_categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('book_categories')->onDelete('cascade')->after('id');
            }
        });
    }
    }

    public function down(): void
    {
        Schema::table('book_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
