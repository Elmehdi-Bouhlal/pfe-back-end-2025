<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\OrderItem;
use App\Models\ReadingProgress;
use App\Models\ReadingNote;
use App\Models\BookDownload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class UserBooksController extends Controller
{
    /**
     * Get all books owned by the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $filter = $request->get('filter', 'all');
            $perPage = $request->get('per_page', 20);

            // Récupérer les IDs des livres achetés avec paiement complété
            $purchasedBookIds = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->where('payment_status', 'completed');
            })
                ->pluck('book_id')
                ->unique()
                ->toArray();

            if (empty($purchasedBookIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0
                    ],
                    'stats' => $this->getUserBookStats($user->id)
                ]);
            }

            // Construire la requête pour les livres
            $query = Book::with([
                'images' => function ($q) {
                    $q->orderBy('sort_order')->where('is_primary', true);
                },
                'digitalFiles' => function ($q) {
                    $q->where('is_active', true);
                }
            ])->whereIn('id', $purchasedBookIds);

            // Appliquer les filtres
            switch ($filter) {
                case 'digital':
                    $query->where('book_type', 'digital');
                    break;
                case 'physical':
                    $query->where('book_type', 'physical');
                    break;
                case 'recent':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $books = $query->paginate($perPage);

            // Enrichir avec les données utilisateur
            foreach ($books as $book) {
                $this->enhanceBookWithUserData($book, $user->id);
            }

            return response()->json([
                'success' => true,
                'data' => $books,
                'stats' => $this->getUserBookStats($user->id)
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching user books', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your books'
            ], 500);
        }
    }

    /**
     * Update reading progress for a digital book
     */
    public function updateProgress(Request $request, int $bookId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'progress' => 'required|integer|min:0|max:100',
                'current_page' => 'nullable|integer|min:1',
                'reading_time' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data provided',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $book = Book::findOrFail($bookId);

            // Vérifier que l'utilisateur possède ce livre
            if (!$this->userOwnsBook($user->id, $bookId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this book'
                ], 403);
            }

            // Mettre à jour ou créer la progression
            $progressData = [
                'progress_percentage' => $request->input('progress'),
                'current_page' => $request->input('current_page', 1),
                'last_read_at' => now(),
                'updated_at' => now()
            ];

            if ($request->input('reading_time')) {
                $progressData['total_reading_time'] = DB::raw('total_reading_time + ' . $request->input('reading_time'));
            }

            if ($request->input('progress') == 100) {
                $progressData['is_completed'] = true;
                $progressData['completed_at'] = now();
            }

            ReadingProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $bookId
                ],
                $progressData
            );

            Log::info('Reading progress updated', [
                'user_id' => $user->id,
                'book_id' => $bookId,
                'progress' => $request->input('progress'),
                'page' => $request->input('current_page', 1)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progress updated successfully',
                'data' => [
                    'progress' => $request->input('progress'),
                    'current_page' => $request->input('current_page', 1),
                    'last_read_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error updating reading progress', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress'
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific book owned by the user
     */
    public function show(int $bookId): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier que l'utilisateur possède ce livre
            if (!$this->userOwnsBook($user->id, $bookId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this book'
                ], 403);
            }

            $book = Book::with([
                'images' => function ($q) {
                    $q->orderBy('sort_order');
                },
                'digitalFiles' => function ($q) {
                    $q->where('is_active', true);
                },
                'user' => function ($q) { // Vendeur du livre
                    $q->select('id', 'name', 'email');
                }
            ])->findOrFail($bookId);

            // Enrichir avec les données utilisateur
            $this->enhanceBookWithUserData($book, $user->id);

            // Ajouter les URLs des images
            foreach ($book->images as $image) {
                $image->url = asset('storage/' . $image->image_path);
            }

            // Ajouter les URLs des fichiers numériques
            foreach ($book->digitalFiles as $file) {
                $file->download_url = route('user.books.download', $bookId);
                $file->file_size_human = $this->formatFileSize($file->file_size);
            }

            // Ajouter les notes de lecture
            $book->reading_notes = $this->getUserBookNotes($user->id, $bookId);

            // Ajouter les informations d'achat
            $purchaseInfo = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('payment_status', 'completed');
            })
                ->where('book_id', $bookId)
                ->with('order')
                ->first();

            if ($purchaseInfo) {
                $book->purchase_info = [
                    'order_number' => $purchaseInfo->order->order_number,
                    'purchase_date' => $purchaseInfo->created_at,
                    'purchase_price' => $purchaseInfo->unit_price,
                    'can_download' => $purchaseInfo->can_download,
                    'download_count' => $purchaseInfo->download_count,
                    'download_limit' => $purchaseInfo->download_limit
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $book
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching user book details', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch book details'
            ], 500);
        }
    }

    /**
     * Download a digital book that the user owns
     */
    public function download(int $bookId)
    {
        try {
            $user = auth()->user();

            // Vérifier que l'utilisateur possède ce livre numérique
            $book = Book::where('id', $bookId)
                ->where('book_type', 'digital')
                ->first();

            if (!$book || !$this->userOwnsBook($user->id, $bookId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book not found or you do not have access to download it'
                ], 404);
            }

            // Vérifier les limites de téléchargement
            $orderItem = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('payment_status', 'completed');
            })
                ->where('book_id', $bookId)
                ->first();

            // if (!$orderItem || !$orderItem->can_download) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Download not allowed'
            //     ], 403);
            // }

            // Récupérer le fichier PDF
            $digitalFile = $book->digitalFiles()->where('is_active', true)->first();

            if (!$digitalFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Digital file not found'
                ], 404);
            }
            Log::info("file path : " . $book->file_path);
            $filePath = storage_path('app/public/' . $digitalFile->file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on server'
                ], 404);
            }

            // Logger le téléchargement
            $this->logBookDownload($user->id, $bookId, $digitalFile);

            // Incrémenter le compteur de téléchargements
            $orderItem->increment('download_count');
            $book->increment('download_count');

            $filename = $book->title . '.' . ($book->file_format ?? 'pdf');
            $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);

        } catch (Exception $e) {
            Log::error('Error downloading book', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download book'
            ], 500);
        }
    }

    /**
     * Add reading notes for a book
     */
    public function addNote(Request $request, int $bookId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:5000',
                'page_number' => 'nullable|integer|min:1',
                'chapter' => 'nullable|string|max:255',
                'note_type' => 'nullable|in:highlight,note,bookmark,question'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data provided',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            // Vérifier que l'utilisateur possède ce livre
            if (!$this->userOwnsBook($user->id, $bookId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this book'
                ], 403);
            }

            // Créer la note
            $note = ReadingNote::create([
                'user_id' => $user->id,
                'book_id' => $bookId,
                'content' => $request->input('content'),
                'page_number' => $request->input('page_number'),
                'chapter' => $request->input('chapter'),
                'note_type' => $request->input('note_type', 'note')
            ]);

            Log::info('Reading note added', [
                'user_id' => $user->id,
                'book_id' => $bookId,
                'note_id' => $note->id,
                'note_type' => $request->input('note_type', 'note')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => [
                    'note_id' => $note->id,
                    'created_at' => $note->created_at->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error adding reading note', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add note'
            ], 500);
        }
    }

    /**
     * Get reading notes for a specific book
     */
    public function getNotes(int $bookId): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier que l'utilisateur possède ce livre
            if (!$this->userOwnsBook($user->id, $bookId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this book'
                ], 403);
            }

            $notes = $this->getUserBookNotes($user->id, $bookId);

            return response()->json([
                'success' => true,
                'data' => $notes
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching reading notes', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notes'
            ], 500);
        }
    }

    /**
     * Delete a reading note
     */
    public function deleteNote(int $bookId, int $noteId): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier et supprimer la note
            $note = ReadingNote::where('id', $noteId)
                ->where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->first();

            if (!$note) {
                return response()->json([
                    'success' => false,
                    'message' => 'Note not found or you do not have permission to delete it'
                ], 404);
            }

            $note->delete();

            Log::info('Reading note deleted', [
                'user_id' => $user->id,
                'book_id' => $bookId,
                'note_id' => $noteId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Error deleting reading note', [
                'user_id' => auth()->id(),
                'book_id' => $bookId,
                'note_id' => $noteId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete note'
            ], 500);
        }
    }

    /**
     * Get reading statistics for the user
     */
    public function getReadingStats(): JsonResponse
    {
        try {
            $user = auth()->user();
            $stats = $this->getUserBookStats($user->id);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching reading stats', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reading statistics'
            ], 500);
        }
    }

    /**
     * Get reading recommendations based on user's library
     */
    public function getRecommendations(): JsonResponse
    {
        try {
            $user = auth()->user();

            // Récupérer les genres favoris de l'utilisateur
            $favoriteGenres = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('payment_status', 'completed');
            })
                ->join('books', 'order_items.book_id', '=', 'books.id')
                ->whereNotNull('books.genre')
                ->select('books.genre', DB::raw('count(*) as count'))
                ->groupBy('books.genre')
                ->orderBy('count', 'desc')
                ->limit(3)
                ->pluck('genre')
                ->toArray();

            // Récupérer les livres que l'utilisateur ne possède pas dans ses genres favoris
            $ownedBookIds = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('payment_status', 'completed');
            })
                ->pluck('book_id')
                ->toArray();

            $recommendations = Book::whereIn('genre', $favoriteGenres)
                ->whereNotIn('id', $ownedBookIds)
                ->where('status', 'published')
                ->where('is_available', true)
                ->with([
                    'images' => function ($q) {
                        $q->where('is_primary', true);
                    }
                ])
                ->orderBy('view_count', 'desc')
                ->limit(10)
                ->get();

            // Ajouter les images de couverture
            foreach ($recommendations as $book) {
                if ($book->images->count() > 0) {
                    $book->cover_image = asset('storage/' . $book->images->first()->image_path);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'based_on_genres' => $favoriteGenres
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching recommendations', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recommendations'
            ], 500);
        }
    }

    // Méthodes privées

    /**
     * Vérifier si l'utilisateur possède un livre spécifique
     */
    private function userOwnsBook(int $userId, int $bookId): bool
    {
        return OrderItem::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('payment_status', 'completed');
        })
            ->where('book_id', $bookId)
            ->exists();
    }

    /**
     * Enrichir un livre avec les données spécifiques à l'utilisateur
     */
    private function enhanceBookWithUserData($book, int $userId): void
    {
        // Ajouter l'URL de l'image de couverture
        if ($book->images->count() > 0) {
            $book->cover_image = asset('storage/' . $book->images->first()->image_path);
        }

        // Ajouter l'URL du PDF pour les livres numériques
        if ($book->book_type === 'digital' && $book->digitalFiles->count() > 0) {
            $pdfFile = $book->digitalFiles->first();
            if ($pdfFile) {
                $book->pdf_url = route('user.books.download', $book->id);
                $book->file_size = $pdfFile->file_size;
                $book->file_size_human = $this->formatFileSize($pdfFile->file_size);
            }
        }

        // Ajouter la progression de lecture pour les livres numériques
        if ($book->book_type === 'digital') {
            $progress = ReadingProgress::where('user_id', $userId)
                ->where('book_id', $book->id)
                ->first();

            $book->reading_progress = $progress ? $progress->progress_percentage : 0;
            $book->last_read_page = $progress ? $progress->current_page : 1;
            $book->reading_time = $progress ? $progress->total_reading_time : 0;
            $book->last_read_at = $progress ? $progress->last_read_at : null;
            $book->is_completed = $progress ? ($progress->progress_percentage >= 100) : false;
        }

        // Ajouter les informations d'achat
        $orderItem = OrderItem::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('payment_status', 'completed');
        })
            ->where('book_id', $book->id)
            ->first();

        if ($orderItem) {
            $book->purchased_at = $orderItem->created_at;
            $book->purchase_price = $orderItem->unit_price;
        }
    }

    /**
     * Obtenir les statistiques des livres de l'utilisateur
     */
    private function getUserBookStats(int $userId): array
    {
        // Récupérer tous les livres possédés
        $ownedBookIds = OrderItem::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('payment_status', 'completed');
        })
            ->pluck('book_id')
            ->unique();

        $totalBooks = $ownedBookIds->count();

        if ($totalBooks === 0) {
            return [
                'total' => 0,
                'digital' => 0,
                'physical' => 0,
                'readingTime' => 0,
                'completed' => 0,
                'currentlyReading' => 0,
                'favoriteGenres' => [],
                'totalReadingMinutes' => 0
            ];
        }

        // Statistiques par type
        $digitalBooks = Book::whereIn('id', $ownedBookIds)
            ->where('book_type', 'digital')
            ->count();

        $physicalBooks = $totalBooks - $digitalBooks;

        // Temps de lecture total
        $totalReadingTime = ReadingProgress::where('user_id', $userId)
            ->whereIn('book_id', $ownedBookIds)
            ->sum('total_reading_time');

        $readingTimeHours = round($totalReadingTime / 60, 1);

        // Livres terminés
        $completedBooks = ReadingProgress::where('user_id', $userId)
            ->whereIn('book_id', $ownedBookIds)
            ->where('is_completed', true)
            ->count();

        // En cours de lecture
        $currentlyReading = ReadingProgress::where('user_id', $userId)
            ->whereIn('book_id', $ownedBookIds)
            ->whereBetween('progress_percentage', [1, 99])
            ->count();

        // Genres favoris
        $favoriteGenres = OrderItem::whereHas('order', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('payment_status', 'completed');
        })
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->whereNotNull('books.genre')
            ->select('books.genre', DB::raw('count(*) as count'))
            ->groupBy('books.genre')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->pluck('genre')
            ->toArray();

        return [
            'total' => $totalBooks,
            'digital' => $digitalBooks,
            'physical' => $physicalBooks,
            'readingTime' => $readingTimeHours,
            'completed' => $completedBooks,
            'currentlyReading' => $currentlyReading,
            'favoriteGenres' => $favoriteGenres,
            'totalReadingMinutes' => $totalReadingTime
        ];
    }

    /**
     * Obtenir les notes de lecture pour un utilisateur et un livre
     */
    private function getUserBookNotes(int $userId, int $bookId): array
    {
        return ReadingNote::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->orderBy('page_number')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Logger le téléchargement d'un livre
     */
    private function logBookDownload(int $userId, int $bookId, $digitalFile): void
    {
        try {
            BookDownload::create([
                'user_id' => $userId,
                'book_id' => $bookId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'download_type' => 'pdf',
                'file_size' => $digitalFile->file_size,
                'download_completed' => true
            ]);

            Log::info('Book download logged', [
                'user_id' => $userId,
                'book_id' => $bookId,
                'file_size' => $digitalFile->file_size
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to log book download', [
                'user_id' => $userId,
                'book_id' => $bookId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Formater la taille de fichier en format lisible
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}