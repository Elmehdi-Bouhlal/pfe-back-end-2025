<?php

// =====================================================
// FICHIER: 2024_01_01_000011_create_book_reports_table.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('book_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->enum('report_reason', ['inappropriate', 'spam', 'fraud', 'copyright', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['book_id']);
            $table->index(['reporter_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('book_reports');
    }
};

?>
