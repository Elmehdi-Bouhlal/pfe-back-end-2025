<?php

// =====================================================
// FICHIER: 2024_01_01_000006_create_book_views_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')
                  ->comment('NULL pour visiteurs anonymes');
            $table->string('ip_address', 45)->nullable()->comment('IP du visiteur');
            $table->text('user_agent')->nullable()->comment('Navigateur du visiteur');
            $table->timestamp('viewed_at')->useCurrent();
            
            $table->index(['book_id']);
            $table->index(['user_id']);
            $table->index(['viewed_at']);
            $table->index(['ip_address']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_views');
    }
};

?>