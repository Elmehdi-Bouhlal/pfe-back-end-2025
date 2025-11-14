<?php
// database/migrations/xxxx_xx_xx_create_order_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->string('book_title'); // Snapshot of title at purchase time
            $table->string('book_author'); // Snapshot of author at purchase time
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 10, 2); // unit_price * quantity
            $table->enum('book_type', ['physical', 'digital']);
            $table->string('book_condition')->nullable();
            $table->json('book_metadata')->nullable(); // Snapshot of book details
            
            // Digital book specific
            $table->string('download_link')->nullable();
            $table->integer('download_count')->default(0);
            $table->integer('download_limit')->nullable();
            $table->timestamp('download_expires_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['order_id']);
            $table->index(['book_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};