<?php

// =====================================================
// FICHIER: 2024_01_01_000013_seed_book_categories.php
// =====================================================
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Catégories principales
        $categories = [
            ['name' => 'Fiction', 'slug' => 'fiction', 'description' => 'Livres de fiction et romans', 'sort_order' => 1],
            ['name' => 'Non-Fiction', 'slug' => 'non-fiction', 'description' => 'Livres documentaires et essais', 'sort_order' => 2],
            ['name' => 'Sciences', 'slug' => 'sciences', 'description' => 'Livres scientifiques et techniques', 'sort_order' => 3],
            ['name' => 'Histoire', 'slug' => 'histoire', 'description' => 'Livres d\'histoire et biographies', 'sort_order' => 4],
            ['name' => 'Art & Culture', 'slug' => 'art-culture', 'description' => 'Livres d\'art, culture et loisirs', 'sort_order' => 5],
            ['name' => 'Éducation', 'slug' => 'education', 'description' => 'Livres scolaires et universitaires', 'sort_order' => 6],
            ['name' => 'Enfants', 'slug' => 'enfants', 'description' => 'Livres pour enfants et jeunesse', 'sort_order' => 7],
            ['name' => 'Religion', 'slug' => 'religion', 'description' => 'Livres religieux et spirituels', 'sort_order' => 8],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Livres de gestion et économie', 'sort_order' => 9],
            ['name' => 'Santé', 'slug' => 'sante', 'description' => 'Livres de santé et bien-être', 'sort_order' => 10],
        ];

        foreach ($categories as $category) {
            DB::table('book_categories')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Sous-catégories pour Fiction (parent_id = 1)
        $subCategories = [
            ['name' => 'Roman', 'slug' => 'roman', 'description' => 'Romans littéraires', 'parent_id' => 1, 'sort_order' => 1],
            ['name' => 'Science-Fiction', 'slug' => 'science-fiction', 'description' => 'Livres de science-fiction', 'parent_id' => 1, 'sort_order' => 2],
            ['name' => 'Fantasy', 'slug' => 'fantasy', 'description' => 'Livres de fantasy et fantastique', 'parent_id' => 1, 'sort_order' => 3],
            ['name' => 'Policier/Thriller', 'slug' => 'policier-thriller', 'description' => 'Romans policiers et thrillers', 'parent_id' => 1, 'sort_order' => 4],
            ['name' => 'Romance', 'slug' => 'romance', 'description' => 'Romans sentimentaux', 'parent_id' => 1, 'sort_order' => 5],
        ];

        foreach ($subCategories as $subCategory) {
            DB::table('book_categories')->insert(array_merge($subCategory, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down()
    {
        DB::table('book_categories')->truncate();
    }
};

?>