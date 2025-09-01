<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\BookLike;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoritesController extends Controller
{
    /**
     * Récupère la liste des favoris de l'utilisateur
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            
            $query = BookLike::where('user_id', $user->id)
                ->with([
                    'book' => function($q) {
                        $q->select([
                            'id', 'title', 'author', 'isbn', 'description', 
                            'genre', 'language', 'book_type', 'book_condition', 
                            'price', 'original_price', 'currency', 'is_available', 
                            'status', 'location_city', 'location_region', 
                            'location_country', 'created_at', 'updated_at'
                        ]);
                    },
                    'book.images' => function($q) {
                        $q->select('id', 'book_id', 'image_url', 'is_primary')
                          ->orderBy('is_primary', 'desc')
                          ->orderBy('created_at', 'asc');
                    }
                ])
                ->orderBy('created_at', 'desc');
            
            $favorites = $query->paginate($perPage);
            
            // Ajouter l'URL de la première image à chaque livre
            foreach ($favorites->items() as $favorite) {
                if ($favorite->book && $favorite->book->images->isNotEmpty()) {
                    $favorite->book->image_url = $favorite->book->images->first()->image_url;
                } else {
                    $favorite->book->image_url = null;
                }
                
                // Nettoyer la relation images pour éviter de la renvoyer
                unset($favorite->book->images);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Favoris récupérés avec succès',
                'data' => [
                    'favorites' => $favorites->items(),
                    'pagination' => [
                        'current_page' => $favorites->currentPage(),
                        'per_page' => $favorites->perPage(),
                        'total' => $favorites->total(),
                        'last_page' => $favorites->lastPage(),
                    ],
                    'has_more' => $favorites->hasMorePages()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Ajoute ou retire un livre des favoris
     */
    public function toggle(Request $request, $bookId)
    {
        try {
            $user = Auth::user();
            
            // Vérifier que le livre existe
            $book = Book::findOrFail($bookId);
            
            // Vérifier si déjà en favori
            $existingFavorite = BookLike::where([
                'user_id' => $user->id,
                'book_id' => $bookId
            ])->first();
            
            if ($existingFavorite) {
                // Retirer des favoris
                $existingFavorite->delete();
                $isFavorited = false;
                $message = 'Livre retiré des favoris';
            } else {
                // Ajouter aux favoris
                BookLike::create([
                    'user_id' => $user->id,
                    'book_id' => $bookId
                ]);
                $isFavorited = true;
                $message = 'Livre ajouté aux favoris';
            }
            
            // Compter le nombre total de favoris pour ce livre
            $favoritesCount = BookLike::where('book_id', $bookId)->count();
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'is_favorited' => $isFavorited,
                    'favorites_count' => $favoritesCount
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Vérifie si un livre est dans les favoris
     */
    public function checkStatus(Request $request, $bookId)
    {
        try {
            $user = Auth::user();
            
            $isFavorited = BookLike::where([
                'user_id' => $user->id,
                'book_id' => $bookId
            ])->exists();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_favorited' => $isFavorited
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Supprime plusieurs favoris en une fois
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'favorite_ids' => 'required|array',
            'favorite_ids.*' => 'required|integer|exists:book_likes,id'
        ]);
        
        try {
            $user = Auth::user();
            
            $deletedCount = BookLike::whereIn('id', $request->favorite_ids)
                ->where('user_id', $user->id)
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => "$deletedCount favori(s) supprimé(s)",
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Exporte les favoris de l'utilisateur
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        try {
            $user = Auth::user();
            
            $favorites = BookLike::where('user_id', $user->id)
                ->with('book')
                ->orderBy('created_at', 'desc')
                ->get();
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCSV($favorites);
                case 'json':
                    return $this->exportToJSON($favorites);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Format non supporté'
                    ], 400);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Exporte en CSV
     */
    private function exportToCSV($favorites)
    {
        $filename = 'favoris_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        
        $callback = function() use ($favorites) {
            $file = fopen('php://output', 'w');
            
            // En-têtes CSV
            fputcsv($file, [
                'ID', 'Titre', 'Auteur', 'Prix', 'Condition', 
                'Genre', 'Langue', 'Disponible', 'Ajouté le'
            ]);
            
            foreach ($favorites as $favorite) {
                fputcsv($file, [
                    $favorite->book->id,
                    $favorite->book->title,
                    $favorite->book->author,
                    $favorite->book->price . ' ' . $favorite->book->currency,
                    $favorite->book->book_condition,
                    $favorite->book->genre ?? 'N/A',
                    $favorite->book->language,
                    $favorite->book->is_available ? 'Oui' : 'Non',
                    $favorite->created_at->format('d/m/Y H:i')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * Exporte en JSON
     */
    private function exportToJSON($favorites)
    {
        $filename = 'favoris_' . date('Y-m-d') . '.json';
        
        $data = $favorites->map(function($favorite) {
            return [
                'id' => $favorite->book->id,
                'title' => $favorite->book->title,
                'author' => $favorite->book->author,
                'price' => $favorite->book->price,
                'currency' => $favorite->book->currency,
                'condition' => $favorite->book->book_condition,
                'genre' => $favorite->book->genre,
                'language' => $favorite->book->language,
                'is_available' => $favorite->book->is_available,
                'added_at' => $favorite->created_at->toISOString()
            ];
        });
        
        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"$filename\"");
    }
}
