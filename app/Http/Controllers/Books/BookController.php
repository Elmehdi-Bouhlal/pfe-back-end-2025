<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookImage;
use App\Models\BookDigitalFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Log;

class BookController extends Controller
{
    /**
     * Store a new book (physical or digital)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Déterminer le type de livre
            $bookType = $request->input('book_type', 'physical');
            
            // Validation rules de base
            $baseRules = [
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:150',
                'price' => 'required|numeric|min:0',
                'genre' => 'nullable|string|max:50',
                'language' => 'nullable|in:english,french,arabic,spanish,other',
                'description' => 'nullable|string',
                'isbn' => 'nullable|string|max:20',
                'is_available' => 'nullable|boolean',
                'book_type' => 'required|in:physical,digital',
                'currency' => 'nullable|string|max:3',
                'status' => 'nullable|in:draft,published,sold,removed,pending_approval',
                'pages' => 'nullable|integer|min:1',
            ];
            
            // Règles spécifiques selon le type de livre
            if ($bookType === 'physical') {
                $specificRules = [
                    'book_condition' => 'required|in:new,like-new,good,fair,poor',
                    'images' => 'nullable|array|max:5',
                    'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
                    'quantity' => 'required|integer|min:1|max:999',
                    'payment_methods' => 'nullable|array',
                    'payment_methods.*' => 'in:cash,card,bank_transfer,mobile_money',
                    'shipping_delay_days' => 'nullable|integer|min:1|max:30',
                    'shipping_cities' => 'nullable|array',
                    'shipping_cities.*' => 'string|max:100',
                    'shipping_cost' => 'nullable|numeric|min:0',
                    'free_shipping_above' => 'nullable|boolean',
                    'free_shipping_threshold' => 'nullable|numeric|min:0',
                ];
            } else { // digital
                $specificRules = [
                    'pdf_file' => 'required|file|mimes:pdf|max:151200', // 50MB max
                    'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
                    'description' => 'required|string', // Obligatoire pour les livres numériques
                    'download_limit' => 'nullable|integer|min:1|max:10',
                    'sample_content' => 'nullable|string',
                ];
            }
            
            $validator = Validator::make($request->all(), array_merge($baseRules, $specificRules));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start database transaction
            DB::beginTransaction();

            // Préparer les données communes
            $bookData = [
                'title' => $request->input('title'),
                'author' => $request->input('author'),
                'price' => $request->input('price'),
                'genre' => $request->input('genre'),
                'language' => $request->input('language', 'french'),
                'description' => $request->input('description'),
                'isbn' => $request->input('isbn'),
                'book_type' => $bookType,
                'currency' => $request->input('currency', 'MAD'),
                'status' => $request->input('status', 'published'),
                'is_available' => $request->boolean('is_available', true),
                'location_country' => 'MA',
                'user_id' => auth()->id(),
                'pages' => $request->input('pages'),
            ];

            // Ajouter des données spécifiques selon le type
            if ($bookType === 'physical') {
                $bookData['book_condition'] = $request->input('book_condition');
                $bookData['quantity'] = $request->input('quantity');
                $bookData['shipping_delay_days'] = $request->input('shipping_delay_days');
                $bookData['shipping_cost'] = $request->input('shipping_cost');
                $bookData['free_shipping_above'] = $request->boolean('free_shipping_above', false);
                $bookData['free_shipping_threshold'] = $request->input('free_shipping_threshold');
                
                // Traitement des arrays pour livres physiques
                if ($request->has('payment_methods') && is_array($request->input('payment_methods'))) {
                    $bookData['payment_methods'] = json_encode($request->input('payment_methods'));
                }
                
                if ($request->has('shipping_cities') && is_array($request->input('shipping_cities'))) {
                    $bookData['shipping_cities'] = json_encode($request->input('shipping_cities'));
                }
            } else { // digital
                $bookData['download_limit'] = $request->input('download_limit');
                $bookData['sample_content'] = $request->input('sample_content');
                // Pour les livres numériques, pas de condition physique
                $bookData['book_condition'] = null;
                $bookData['quantity'] = 1; // Toujours 1 pour les livres numériques
            }

            // Create book record
            $book = Book::create($bookData);

            // Gérer les fichiers selon le type
            if ($bookType === 'physical') {
                // Handle image uploads for physical books
                if ($request->hasFile('images')) {
                    $this->handleImageUploads($book, $request->file('images'));
                }
            } else { // digital
                // Handle PDF and cover image for digital books
                if ($request->hasFile('pdf_file')) {
                    $this->handlePdfUpload($book, $request->file('pdf_file'));
                }
                
                if ($request->hasFile('cover_image')) {
                    $this->handleCoverImageUpload($book, $request->file('cover_image'));
                }
            }

            // Commit transaction
            DB::commit();

            // Load book with relations for response
            $book->load(['images', 'digitalFiles']);

            return response()->json([
                'success' => true,
                'message' => ucfirst($bookType) . ' book created successfully',
                'data' => [
                    'book' => $book,
                    'type' => $bookType
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error creating book: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create book. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle image uploads for physical books
     *
     * @param Book $book
     * @param array $images
     * @return void
     */
    private function handleImageUploads(Book $book, array $images): void
    {
        foreach ($images as $index => $image) {
            $filename = time() . '_' . $index . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('books/images', $filename, 'public');
            
            Log::info('Physical book image path: ' . $path);

            BookImage::create([
                'book_id' => $book->id,
                'image_path' => $path,
                'image_name' => $filename,
                'image_size' => $image->getSize(),
                'image_type' => $image->getClientMimeType(),
                'sort_order' => $index,
                'is_primary' => $index === 0 ? 1 : 0,
                'alt_text' => $book->title ?? 'book image',
            ]);
        }
    }

    /**
     * Handle PDF upload for digital books
     *
     * @param Book $book
     * @param \Illuminate\Http\UploadedFile $pdfFile
     * @return void
     */
    private function handlePdfUpload(Book $book, $pdfFile): void
    {
        try {
            $filename = time() . '_' . Str::random(10) . '.pdf';
            $path = $pdfFile->storeAs('books/pdf', $filename, 'public');
            
            Log::info('PDF upload path: ' . $path);
            
            BookDigitalFile::create([
                'book_id' => $book->id,
                'file_type' => 'pdf',
                'file_path' => $path,
                'file_name' => $pdfFile->getClientOriginalName(),
                'file_size' => $pdfFile->getSize(),
                'mime_type' => $pdfFile->getMimeType(),
                'is_active' => true,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error uploading PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle cover image upload for digital books
     *
     * @param Book $book
     * @param \Illuminate\Http\UploadedFile $coverImage
     * @return void
     */
    private function handleCoverImageUpload(Book $book, $coverImage): void
    {
        try {
            $filename = time() . '_cover_' . Str::random(10) . '.' . $coverImage->extension();
            $path = $coverImage->storeAs('books/covers', $filename, 'public');
            
            Log::info('Cover image upload path: ' . $path);
            
            BookImage::create([
                'book_id' => $book->id,
                'image_path' => $path,
                'image_name' => $filename,
                'image_size' => $coverImage->getSize(),
                'image_type' => 'cover',
                'sort_order' => 0,
                'is_primary' => true,
                'alt_text' => $book->title . ' cover',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error uploading cover image: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all books with pagination and filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);

            // Start building query
            $query = Book::with([
                'images' => function ($q) {
                    $q->orderBy('sort_order');
                },
                'digitalFiles' => function ($q) {
                    $q->where('is_active', true);
                }
            ]);

            // Apply search filter
            if ($request->has('q') && !empty($request->get('q'))) {
                $searchTerm = $request->get('q');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('author', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('isbn', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('genre', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply filters
            if ($request->has('book_type') && !empty($request->get('book_type'))) {
                $query->where('book_type', $request->get('book_type'));
            }

            if ($request->has('language') && !empty($request->get('language'))) {
                $query->where('language', $request->get('language'));
            }

            if ($request->has('genre') && !empty($request->get('genre'))) {
                $query->where('genre', $request->get('genre'));
            }

            if ($request->has('book_condition') && !empty($request->get('book_condition'))) {
                $query->where('book_condition', $request->get('book_condition'));
            }

            // Apply price range filters
            if ($request->has('min_price') && is_numeric($request->get('min_price'))) {
                $query->where('price', '>=', $request->get('min_price'));
            }

            if ($request->has('max_price') && is_numeric($request->get('max_price'))) {
                $query->where('price', '<=', $request->get('max_price'));
            }

            // Default ordering
            $query->orderBy('created_at', 'desc');

            // Execute query with pagination
            $books = $query->paginate($perPage);

            // Add primary image URLs
            foreach ($books as $book) {
                if (isset($book->images) && count($book->images) > 0) {
                    $book->primary_image = "http://localhost:8000/storage/" . $book->images[0]->image_path;
                }
                
                // Add PDF download URL for digital books
                if ($book->book_type === 'digital' && $book->digitalFiles->count() > 0) {
                    $pdfFile = $book->digitalFiles->where('file_type', 'pdf')->first();
                    if ($pdfFile) {
                        $book->pdf_download_url = "http://localhost:8000/storage/" . $pdfFile->file_path;
                        $book->pdf_size = $pdfFile->file_size;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $books
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching books: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch books',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific book by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $book = Book::with([
                'images' => function ($query) {
                    $query->orderBy('sort_order');
                },
                'digitalFiles' => function ($query) {
                    $query->where('is_active', true);
                }
            ])->findOrFail($id);

            // Add full URLs for images
            if ($book->images->count() > 0) {
                foreach ($book->images as $image) {
                    $image->full_url = "http://localhost:8000/storage/" . $image->image_path;
                }
            }

            // Add PDF info for digital books
            if ($book->book_type === 'digital' && $book->digitalFiles->count() > 0) {
                $pdfFile = $book->digitalFiles->where('file_type', 'pdf')->first();
                if ($pdfFile) {
                    $book->pdf_info = [
                        'download_url' => "http://localhost:8000/storage/" . $pdfFile->file_path,
                        'file_size' => $pdfFile->file_size,
                        'file_name' => $pdfFile->file_name,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $book
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error fetching book: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch book',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // ... (gardez les autres méthodes existantes : update, destroy, search, etc.)
    // Elles restent identiques à votre code actuel

    /**
     * Delete book files from storage (images and PDFs)
     *
     * @param Book $book
     * @return void
     */
    private function deleteBookFiles(Book $book): void
    {
        // Delete images
        foreach ($book->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
            $image->delete();
        }

        // Delete digital files (PDFs)
        foreach ($book->digitalFiles as $file) {
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }
            $file->delete();
        }
    }

    /**
     * Download digital book (for purchased users)
     *
     * @param Request $request
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     */
    public function downloadDigitalBook(Request $request, int $id)
    {
        try {
            $book = Book::where('book_type', 'digital')->findOrFail($id);
            
            // TODO: Vérifier si l'utilisateur a acheté ce livre
            // $user = auth()->user();
            // if (!$book->canBeDownloadedBy($user)) {
            //     return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            // }

            $pdfFile = $book->digitalFiles()->where('file_type', 'pdf')->where('is_active', true)->first();
            
            if (!$pdfFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF file not found'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $pdfFile->file_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on server'
                ], 404);
            }

            return response()->download($filePath, $pdfFile->file_name);

        } catch (\Exception $e) {
            \Log::error('Error downloading digital book: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download book'
            ], 500);
        }
    }

    /**
     * Record a view for a book
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function recordView(Request $request, int $id): JsonResponse
    {
        try {
            $book = Book::findOrFail($id);

            // Get user info
            $user = auth()->user();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Use the BookView model method to record view
            \App\Models\BookView::recordView($book, $user, $ipAddress, $userAgent);

            return response()->json([
                'success' => true,
                'message' => 'View recorded successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error recording view: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to record view'
            ], 500);
        }
    }
      /**
     * Search books
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q');
            $perPage = $request->get('per_page', 15);

            $books = Book::with([
                'images' => function ($query) {
                    $query->orderBy('sort_order');
                }
            ])
                // comment for the moment because we are not manage this
                // ->where('status', 'published')
                // ->where('is_available', true)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                        ->orWhere('author', 'LIKE', "%{$query}%")
                        ->orWhere('genre', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $books
            ]);

        } catch (\Exception $e) {
            \Log::error('Error searching books: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to search books',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}