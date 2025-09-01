<?php

// =====================================================
// FICHIER: 2024_01_01_000012_create_book_views.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Vue pour les livres avec leurs images principales
        DB::statement("
            CREATE VIEW books_with_images AS
            SELECT 
                b.*,
                bi.image_path as primary_image,
                bi.alt_text as primary_image_alt,
                (SELECT COUNT(*) FROM book_images WHERE book_id = b.id) as total_images
            FROM books b
            LEFT JOIN book_images bi ON b.id = bi.book_id AND bi.is_primary = TRUE
            WHERE b.deleted_at IS NULL
        ");

        // Vue pour les statistiques utilisateur
        DB::statement("
            CREATE VIEW user_book_stats AS
            SELECT 
                u.id as user_id,
                u.name,
                u.email,
                COUNT(CASE WHEN b.status = 'published' THEN 1 END) as books_listed,
                COUNT(CASE WHEN b.status = 'sold' THEN 1 END) as books_sold,
                COALESCE(AVG(r.rating), 0) as average_rating,
                COUNT(r.id) as total_reviews
            FROM users u
            LEFT JOIN books b ON u.id = b.user_id AND b.deleted_at IS NULL
            LEFT JOIN book_reviews r ON u.id = r.reviewed_user_id AND r.review_type = 'seller'
            GROUP BY u.id, u.name, u.email
        ");
    }

    public function down()
    {
        DB::statement('DROP VIEW IF EXISTS books_with_images');
        DB::statement('DROP VIEW IF EXISTS user_book_stats');
    }
};

?>