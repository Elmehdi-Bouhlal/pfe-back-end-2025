<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Vérifier si la table existe déjà
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                // Créer l'index seulement s'il n'existe pas
                $table->index(['notifiable_type', 'notifiable_id'], 'notifiable_index');
            });
        } else {
            // Si la table existe, vérifier et ajouter seulement les colonnes manquantes
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'id')) {
                    $table->uuid('id')->primary();
                }
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type');
                }
                if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                    $table->string('notifiable_type');
                }
                if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                    $table->unsignedBigInteger('notifiable_id');
                }
                if (!Schema::hasColumn('notifications', 'data')) {
                    $table->text('data');
                }
                if (!Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
                if (!Schema::hasColumn('notifications', 'created_at')) {
                    $table->timestamps();
                }
                
                // Vérifier si l'index existe avant de le créer
                $indexes = Schema::getConnection()
                    ->getDoctrineSchemaManager()
                    ->listTableIndexes('notifications');
                
                $indexExists = false;
                foreach ($indexes as $index) {
                    if (in_array('notifiable_type', $index->getColumns()) && 
                        in_array('notifiable_id', $index->getColumns())) {
                        $indexExists = true;
                        break;
                    }
                }
                
                if (!$indexExists) {
                    $table->index(['notifiable_type', 'notifiable_id'], 'notifiable_index');
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};