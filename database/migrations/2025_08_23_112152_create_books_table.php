<?php

// =====================================================
// FICHIER: create_books_table.php - VERSION CORRIGÉE
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            
            // Informations de base du livre - TAILLES RÉDUITES POUR INDEX
            $table->string('title', 255)->comment('Titre du livre'); // Réduit de 500 à 255
            $table->string('author', 150)->comment('Auteur du livre'); // Réduit de 300 à 150
            $table->string('isbn', 20)->nullable()->comment('Numéro ISBN (optionnel)');
            $table->text('description')->nullable()->comment('Description détaillée du livre');
            
            // Informations de catégorisation
            $table->string('genre', 50)->nullable()->comment('Genre du livre'); // Réduit de 100 à 50
            $table->enum('language', ['english', 'french', 'arabic', 'spanish', 'other'])
                  ->default('french')->comment('Langue du livre');
            
            // Type de livre et condition
            $table->enum('book_type', ['physical', 'digital'])
                  ->default('physical')->comment('Type: physique ou numérique');
            $table->enum('book_condition', ['new', 'like-new', 'good', 'fair', 'poor'])
                  ->nullable()->comment('État du livre (pour livres physiques uniquement)');
            
            // Informations de prix et disponibilité
            $table->decimal('price', 10, 2)->comment('Prix en MAD');
            $table->decimal('original_price', 10, 2)->nullable()->comment('Prix original (optionnel)');
            $table->string('currency', 3)->default('MAD')->comment('Devise');
            $table->boolean('is_available')->default(true)->comment('Disponibilité');
            $table->boolean('is_negotiable')->default(true)->comment('Prix négociable');
            
            // Informations du vendeur
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID du vendeur');
            $table->text('seller_notes')->nullable()->comment('Notes additionnelles du vendeur');
            
            // Métadonnées pour livres numériques
            $table->string('file_path', 500)->nullable()->comment('Chemin vers le fichier PDF');
            $table->bigInteger('file_size')->nullable()->comment('Taille du fichier en bytes');
            $table->string('file_format', 10)->nullable()->comment('Format du fichier (PDF, EPUB, etc.)');
            $table->integer('download_count')->default(0)->comment('Nombre de téléchargements');
            
            // Statistiques et engagement
            $table->integer('view_count')->default(0)->comment('Nombre de vues');
            $table->integer('like_count')->default(0)->comment('Nombre de likes');
            $table->integer('share_count')->default(0)->comment('Nombre de partages');
            
            // Statut de publication
            $table->enum('status', ['draft', 'published', 'sold', 'removed', 'pending_approval'])
                  ->default('draft')->comment('Statut de la publication');
            $table->timestamp('published_at')->nullable()->comment('Date de publication');
            $table->timestamp('sold_at')->nullable()->comment('Date de vente');
            
            // Localisation (pour livres physiques)
            $table->string('location_city', 100)->nullable()->comment('Ville de localisation');
            $table->string('location_region', 100)->nullable()->comment('Région/Province');
            $table->string('location_country', 3)->default('MA')->comment('Code pays');
            
            // Métadonnées système
            $table->timestamps();
            $table->softDeletes();
            
            // Index individuels (pas de problème de taille)
            $table->index(['user_id']);
            $table->index(['book_type']);
            $table->index(['status']);
            $table->index(['genre']);
            $table->index(['language']);
            $table->index(['price']);
            $table->index(['created_at']);
            $table->index(['published_at']);
            $table->index(['location_city']);
            $table->index(['location_region']);
            
            // Index pour recherche textuelle - AVEC LONGUEUR LIMITÉE
            $table->index([DB::raw('title(50)')], 'idx_title'); // Premiers 50 caractères du titre
            $table->index([DB::raw('author(50)')], 'idx_author'); // Premiers 50 caractères de l'auteur
            
            // Index composites avec tailles optimisées
            $table->index(['book_type', 'status'], 'idx_type_status');
            $table->index(['status', 'price'], 'idx_status_price');
            $table->index(['genre', 'language'], 'idx_genre_lang');
            $table->index(['location_city', 'book_type'], 'idx_location_type');
            
            // Index pour tri par popularité
            $table->index(['status', 'view_count'], 'idx_popular_views');
            $table->index(['status', 'like_count'], 'idx_popular_likes');
            $table->index(['status', 'created_at'], 'idx_recent');
        });
        
        // Ajouter index FULLTEXT après création de table (pour recherche)
        Schema::table('books', function (Blueprint $table) {
            DB::statement('ALTER TABLE books ADD FULLTEXT(title, author, description) WITH PARSER ngram');
        });
    }

    public function down()
    {
        Schema::dropIfExists('books');
    }
};

?>