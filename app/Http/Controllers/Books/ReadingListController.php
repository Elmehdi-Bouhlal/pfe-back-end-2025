<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\ReadingList;

class ReadingListController extends Controller
{
    /**
     * Obtenir toutes les listes de lecture de l'utilisateur (format Kanban)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Filtres optionnels
            $type = $request->get('type'); // 'book', 'task'
            $genre = $request->get('genre');

            $query = ReadingList::forUser($user->id)->ordered();

            if ($type) {
                $query->ofType($type);
            }

            if ($genre && $type === 'book') {
                $query->byGenre($genre);
            }

            $items = $query->get();

            // Organiser en format Kanban
            $kanbanData = [
                'to_read' => [
                    'title' => 'À Lire',
                    'items' => $items->where('status', 'to_read')->values()
                ],
                'reading' => [
                    'title' => 'En Cours',
                    'items' => $items->where('status', 'reading')->values()
                ],
                'completed' => [
                    'title' => 'Terminés',
                    'items' => $items->where('status', 'completed')->values()
                ]
            ];

            // Obtenir les statistiques
            $stats = ReadingList::getUserStats($user->id);

            // Obtenir les genres disponibles
            $genres = ReadingList::getUserGenres($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'kanban' => $kanbanData,
                    'stats' => $stats,
                    'genres' => $genres
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching reading list: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la liste de lecture'
            ], 500);
        }
    }

    /**
     * Créer un nouvel élément (livre ou tâche)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'type' => 'required|in:book,task',
                'status' => 'nullable|in:to_read,reading,completed',
                'priority' => 'nullable|in:low,medium,high',
                
                // Champs livres
                'author' => 'required_if:type,book|string|max:255',
                'genre' => 'nullable|string|max:100',
                'total_pages' => 'nullable|integer|min:1|max:10000',
                'current_page' => 'nullable|integer|min:0',
                'isbn' => 'nullable|string|max:20',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                
                // Champs tâches
                'due_date' => 'nullable|date|after_or_equal:today',
                'progress' => 'nullable|integer|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $data = $validator->validated();
            $data['user_id'] = $user->id;
            $data['status'] = $data['status'] ?? 'to_read';

            // Gérer l'upload de l'image de couverture
            if ($request->hasFile('cover_image')) {
                $imagePath = $request->file('cover_image')->store('reading_covers', 'public');
                $data['cover_image'] = $imagePath;
            }

            // Définir l'ordre dans la colonne
            $maxOrder = ReadingList::forUser($user->id)
                ->withStatus($data['status'])
                ->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;

            // Gérer les dates automatiques
            if ($data['status'] === 'reading') {
                $data['started_at'] = now();
            } elseif ($data['status'] === 'completed') {
                $data['completed_at'] = now();
                if ($data['type'] === 'book' && isset($data['total_pages'])) {
                    $data['current_page'] = $data['total_pages'];
                }
                if ($data['type'] === 'task') {
                    $data['progress'] = 100;
                }
            }

            $item = ReadingList::create($data);

            return response()->json([
                'success' => true,
                'message' => ucfirst($item->type) . ' ajouté(e) avec succès',
                'data' => $item->fresh()
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error creating reading list item: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * Obtenir un élément spécifique
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $item = ReadingList::forUser($user->id)->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $item
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Élément non trouvé'
            ], 404);
        }
    }

    /**
     * Mettre à jour un élément
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $item = ReadingList::forUser($user->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'priority' => 'nullable|in:low,medium,high',
                'rating' => 'nullable|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                
                // Champs livres
                'author' => 'sometimes|required|string|max:255',
                'genre' => 'nullable|string|max:100',
                'total_pages' => 'nullable|integer|min:1|max:10000',
                'current_page' => 'nullable|integer|min:0',
                'isbn' => 'nullable|string|max:20',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
                
                // Champs tâches
                'due_date' => 'nullable|date',
                'progress' => 'nullable|integer|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Gérer l'upload de nouvelle image
            if ($request->hasFile('cover_image')) {
                // Supprimer l'ancienne image
                if ($item->cover_image && Storage::disk('public')->exists($item->cover_image)) {
                    Storage::disk('public')->delete($item->cover_image);
                }
                
                $imagePath = $request->file('cover_image')->store('reading_covers', 'public');
                $data['cover_image'] = $imagePath;
            }

            // Valider les contraintes spécifiques
            if (isset($data['current_page']) && $item->total_pages) {
                $data['current_page'] = min($data['current_page'], $item->total_pages);
            }

            $item->update($data);

            // Auto-compléter si nécessaire
            if ($item->type === 'book' && isset($data['current_page']) && 
                $item->total_pages && $data['current_page'] >= $item->total_pages) {
                $item->markAsCompleted();
            }

            if ($item->type === 'task' && isset($data['progress']) && $data['progress'] >= 100) {
                $item->markAsCompleted();
            }

            return response()->json([
                'success' => true,
                'message' => 'Élément mis à jour avec succès',
                'data' => $item->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating reading list item: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer un élément
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $item = ReadingList::forUser($user->id)->findOrFail($id);

            // Supprimer l'image de couverture si elle existe
            if ($item->cover_image && Storage::disk('public')->exists($item->cover_image)) {
                Storage::disk('public')->delete($item->cover_image);
            }

            $itemTitle = $item->title;
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => "\"$itemTitle\" supprimé avec succès"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Changer le statut d'un élément (drag & drop)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:to_read,reading,completed',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $item = ReadingList::forUser($user->id)->findOrFail($id);
            
            $newStatus = $request->status;
            $item->changeStatus($newStatus);

            // Mettre à jour l'ordre si fourni
            if ($request->has('sort_order')) {
                $item->update(['sort_order' => $request->sort_order]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => $item->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    /**
     * Mettre à jour la progression
     */
    public function updateProgress(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_page' => 'nullable|integer|min:0',
                'progress' => 'nullable|integer|min:0|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $item = ReadingList::forUser($user->id)->findOrFail($id);

            $currentPage = $request->current_page;
            $progress = $request->progress;

            $item->updateProgress($currentPage, $progress);

            return response()->json([
                'success' => true,
                'message' => 'Progression mise à jour',
                'data' => $item->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la progression'
            ], 500);
        }
    }

    /**
     * Ajouter une note et un commentaire
     */
    public function addRating(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $item = ReadingList::forUser($user->id)->findOrFail($id);

            $item->addRating($request->rating, $request->comment);

            return response()->json([
                'success' => true,
                'message' => 'Note ajoutée avec succès',
                'data' => $item->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout de la note'
            ], 500);
        }
    }

    /**
     * Réorganiser les éléments dans une colonne
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:to_read,reading,completed',
                'item_ids' => 'required|array',
                'item_ids.*' => 'integer|exists:reading_lists,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            
            ReadingList::reorderInColumn(
                $user->id, 
                $request->status, 
                $request->item_ids
            );

            return response()->json([
                'success' => true,
                'message' => 'Ordre mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques détaillées
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = ReadingList::getUserStats($user->id);

            // Ajouter des statistiques supplémentaires
            $additionalStats = [
                'current_month' => [
                    'books_completed' => ReadingList::forUser($user->id)
                        ->books()
                        ->withStatus('completed')
                        ->whereMonth('completed_at', now()->month)
                        ->whereYear('completed_at', now()->year)
                        ->count(),
                    'pages_read' => ReadingList::forUser($user->id)
                        ->books()
                        ->withStatus('completed')
                        ->whereMonth('completed_at', now()->month)
                        ->whereYear('completed_at', now()->year)
                        ->sum('total_pages') ?? 0,
                    'tasks_completed' => ReadingList::forUser($user->id)
                        ->tasks()
                        ->withStatus('completed')
                        ->whereMonth('completed_at', now()->month)
                        ->whereYear('completed_at', now()->year)
                        ->count()
                ],
                'reading_streak' => $this->calculateReadingStreak($user->id),
                'favorite_genre' => $this->getFavoriteGenre($user->id),
                'average_reading_time' => $this->getAverageReadingTime($user->id)
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($stats, $additionalStats)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    /**
     * Importer des livres depuis une API externe (Google Books, etc.)
     */
    public function importBook(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'isbn' => 'required|string|max:20',
                'status' => 'nullable|in:to_read,reading,completed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $isbn = $request->isbn;

            // Vérifier si le livre existe déjà
            $existingBook = ReadingList::forUser($user->id)
                ->where('isbn', $isbn)
                ->first();

            if ($existingBook) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce livre est déjà dans votre liste'
                ], 400);
            }

            // Appeler l'API Google Books (exemple)
            $bookData = $this->fetchBookFromAPI($isbn);

            if (!$bookData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livre non trouvé'
                ], 404);
            }

            // Créer l'entrée
            $item = ReadingList::create([
                'user_id' => $user->id,
                'type' => 'book',
                'title' => $bookData['title'],
                'author' => $bookData['author'],
                'genre' => $bookData['genre'] ?? null,
                'total_pages' => $bookData['pages'] ?? null,
                'isbn' => $isbn,
                'cover_image' => $bookData['cover_url'] ?? null,
                'status' => $request->status ?? 'to_read',
                'sort_order' => ReadingList::forUser($user->id)
                    ->withStatus($request->status ?? 'to_read')
                    ->max('sort_order') + 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livre importé avec succès',
                'data' => $item
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'importation'
            ], 500);
        }
    }

    /**
     * Obtenir les livres recommandés basés sur l'historique
     */
    public function getRecommendations(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Obtenir les genres préférés
            $favoriteGenres = ReadingList::forUser($user->id)
                ->books()
                ->whereNotNull('genre')
                ->whereNotNull('rating')
                ->where('rating', '>=', 4)
                ->groupBy('genre')
                ->selectRaw('genre, count(*) as count, avg(rating) as avg_rating')
                ->orderByDesc('avg_rating')
                ->orderByDesc('count')
                ->limit(3)
                ->pluck('genre')
                ->toArray();

            // Ici vous pourriez appeler une API de recommandations
            // Pour l'exemple, on retourne les genres favoris
            return response()->json([
                'success' => true,
                'data' => [
                    'favorite_genres' => $favoriteGenres,
                    'message' => 'Basé sur vos lectures, nous vous recommandons des livres de ces genres'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des recommandations'
            ], 500);
        }
    }

    // =====================
    // MÉTHODES PRIVÉES
    // =====================

    /**
     * Calculer la série de lecture (jours consécutifs)
     */
    private function calculateReadingStreak(int $userId): int
    {
        $streak = 0;
        $currentDate = now()->startOfDay();
        
        while (true) {
            $hasActivity = ReadingList::forUser($userId)
                ->where(function($query) use ($currentDate) {
                    $query->whereDate('started_at', $currentDate)
                          ->orWhereDate('completed_at', $currentDate)
                          ->orWhereDate('updated_at', $currentDate);
                })
                ->exists();
                
            if ($hasActivity) {
                $streak++;
                $currentDate->subDay();
            } else {
                break;
            }
        }
        
        return $streak;
    }

    /**
     * Obtenir le genre favori
     */
    private function getFavoriteGenre(int $userId): ?string
    {
        return ReadingList::forUser($userId)
            ->books()
            ->whereNotNull('genre')
            ->groupBy('genre')
            ->selectRaw('genre, count(*) as count')
            ->orderByDesc('count')
            ->first()
            ->genre ?? null;
    }

    /**
     * Calculer le temps moyen de lecture
     */
    private function getAverageReadingTime(int $userId): ?float
    {
        $completedBooks = ReadingList::forUser($userId)
            ->books()
            ->withStatus('completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedBooks->isEmpty()) {
            return null;
        }

        $totalDays = $completedBooks->sum(function($book) {
            return $book->started_at->diffInDays($book->completed_at) + 1;
        });

        return round($totalDays / $completedBooks->count(), 1);
    }

    /**
     * Récupérer les données d'un livre depuis une API externe
     */
    private function fetchBookFromAPI(string $isbn): ?array
    {
        try {
            // Exemple avec Google Books API
            $response = \Http::get("https://www.googleapis.com/books/v1/volumes", [
                'q' => "isbn:$isbn",
                'key' => config('services.google_books.api_key',"AIzaSyCX1MbsNkeB6EGuX1UOr1PXc65dLqCdeO0") // Configurez votre clé API
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['items'][0]['volumeInfo'])) {
                    $book = $data['items'][0]['volumeInfo'];
                    
                    return [
                        'title' => $book['title'] ?? 'Titre inconnu',
                        'author' => isset($book['authors']) ? implode(', ', $book['authors']) : 'Auteur inconnu',
                        'genre' => isset($book['categories']) ? $book['categories'][0] : null,
                        'pages' => $book['pageCount'] ?? null,
                        'cover_url' => $book['imageLinks']['thumbnail'] ?? null
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            \Log::warning("Failed to fetch book data for ISBN $isbn: " . $e->getMessage());
            return null;
        }
    }
}