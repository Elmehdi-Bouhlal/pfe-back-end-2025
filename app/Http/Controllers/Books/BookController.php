<?php

namespace App\Http\Controllers\Books;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Log;

class BookController extends Controller
{
    /**
     * Store a new book
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validation rules
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:150',
                'price' => 'required|numeric|min:0',
                'book_condition' => 'required|in:new,like-new,good,fair,poor',
                'genre' => 'nullable|string|max:50',
                'language' => 'nullable|in:english,french,arabic,spanish,other',
                'description' => 'nullable|string',
                'isbn' => 'nullable|string|max:20',
                'book_type' => 'nullable|in:physical,digital',
                'currency' => 'nullable|string|max:3',
                'status' => 'nullable|in:draft,published,sold,removed,pending_approval',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB max per image
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Start database transaction
            DB::beginTransaction();

            // Create book record
            $book = Book::create([
                'title' => $request->input('title'),
                'author' => $request->input('author'),
                'price' => $request->input('price'),
                'book_condition' => $request->input('book_condition'),
                'genre' => $request->input('genre'),
                'language' => $request->input('language', 'french'),
                'description' => $request->input('description'),
                'isbn' => $request->input('isbn'),
                'book_type' => $request->input('book_type', 'physical'),
                'currency' => $request->input('currency', 'MAD'),
                'status' => $request->input('status', 'draft'),
                'location_country' => 'MA', // Default Morocco
                'user_id' => auth()->id(), // Add authenticated user ID
            ]);

            // Handle image uploads if present
            if ($request->hasFile('images')) {
                $this->handleImageUploads($book, $request->file('images'));
            }

            // Commit transaction
            DB::commit();

            // Load book with images for response
            $book->load('images');

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => [
                    'book' => $book,
                    'images' => $book->images
                ]
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollback();

            // Log the error
            \Log::error('Error creating book: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create book. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle image uploads for a book
     *
     * @param Book $book
     * @param array $images
     * @return void
     */
    private function handleImageUploads(Book $book, array $images): void
    {
        foreach ($images as $index => $image) {
            $filename = time() . '_' . $index . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

            $path = $image->storeAs('books', $filename, 'public');
            Log::info('path image : ' . $path);

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
            if ($request->has('status') && !empty($request->get('status'))) {
                $query->where('status', $request->get('status'));
            }

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
                }
            ])
                ->findOrFail($id);

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

    /**
     * Update a book
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $book = Book::findOrFail($id);

            // Validation rules
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:150',
                'price' => 'required|numeric|min:0',
                'book_condition' => 'required|in:new,like-new,good,fair,poor',
                'genre' => 'nullable|string|max:50',
                'language' => 'nullable|in:english,french,arabic,spanish,other',
                'description' => 'nullable|string',
                'isbn' => 'nullable|string|max:20',
                'status' => 'nullable|in:draft,published,sold,removed,pending_approval',
                'is_available' => 'nullable|boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update book
            $book->update($validator->validated());

            // Handle new images if present
            if ($request->hasFile('images')) {
                // Optionally delete old images
                // $this->deleteBookImages($book);

                $this->handleImageUploads($book, $request->file('images'));
            }

            DB::commit();

            // Load updated book with images
            $book->load('images');

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => $book
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error updating book: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update book',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a book
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $book = Book::findOrFail($id);

            DB::beginTransaction();

            // Delete associated images
            $this->deleteBookImages($book);

            // Soft delete the book
            $book->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error deleting book: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete book',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete book images from storage and database
     *
     * @param Book $book
     * @return void
     */
    private function deleteBookImages(Book $book): void
    {
        foreach ($book->images as $image) {
            // Delete file from storage
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Delete database record
            $image->delete();
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
                ->where('status', 'published')
                ->where('is_available', true)
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
    /**
     * Bulk delete books
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:books,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid book IDs provided',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bookIds = $request->input('ids');

            // Check if user has permission to delete these books (if using authentication)
            // $books = Book::whereIn('id', $bookIds)->where('user_id', auth()->id())->get();

            $books = Book::whereIn('id', $bookIds)->get();

            if ($books->count() !== count($bookIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some books were not found or you do not have permission to delete them'
                ], 403);
            }

            DB::beginTransaction();

            $deletedCount = 0;
            foreach ($books as $book) {
                // Delete associated images
                $this->deleteBookImages($book);

                // Soft delete the book
                $book->delete();
                $deletedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} books deleted successfully",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error bulk deleting books: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete books',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
