<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Get user's cart items
     */
    public function index()
    {
        try {
            $user = Auth::user();
            
            $cartItems = CartItem::where('user_id', $user->id)
                ->with(['book' => function($query) {
                    $query->select([
                        'id', 'title', 'author', 'price', 'currency', 
                        'is_available', 'status', 'book_type', 'book_condition'
                    ])->with(['images' => function($q) {
                        $q->where('is_primary', true)->select('id', 'book_id', 'image_path');
                    }]);
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate total
            $totalAmount = $cartItems->sum(function($item) {
                return $item->book->price * $item->quantity;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $cartItems,
                    'total_items' => $cartItems->count(),
                    'total_amount' => $totalAmount,
                    'currency' => 'MAD'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add book to cart
     */
    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|integer|exists:books,id',
            'quantity' => 'integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $bookId = $request->input('book_id');
            $quantity = $request->input('quantity', 1);

            // Check if book exists and is available
            $book = Book::where('id', $bookId)
                       ->where('is_available', true)
                       ->where('status', 'published')
                       ->first();

            if (!$book) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book not found or not available'
                ], 404);
            }

            // Check if user is trying to add their own book
            if ($book->user_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot add your own book to cart'
                ], 403);
            }

            // Check if book is already in cart
            $existingCartItem = CartItem::where([
                'user_id' => $user->id,
                'book_id' => $bookId
            ])->first();

            if ($existingCartItem) {
                // Update quantity instead of creating new item
                $existingCartItem->update([
                    'quantity' => $existingCartItem->quantity + $quantity
                ]);
                
                $cartItem = $existingCartItem;
                $message = 'Cart updated with new quantity';
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'user_id' => $user->id,
                    'book_id' => $bookId,
                    'quantity' => $quantity
                ]);
                
                $message = 'Book added to cart successfully';
            }

            // Load book relationship
            $cartItem->load('book');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart_item' => $cartItem
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add book to cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(Request $request, $cartItemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $quantity = $request->input('quantity');

            $cartItem = CartItem::where('id', $cartItemId)
                               ->where('user_id', $user->id)
                               ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartItem->update(['quantity' => $quantity]);
            $cartItem->load('book');

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'cart_item' => $cartItem
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart($cartItemId)
    {
        try {
            $user = Auth::user();

            $cartItem = CartItem::where('id', $cartItemId)
                               ->where('user_id', $user->id)
                               ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Book removed from cart successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove book from cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clearCart()
    {
        try {
            $user = Auth::user();

            $deletedCount = CartItem::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart summary
     */
    public function getCartSummary()
    {
        try {
            $user = Auth::user();

            $cartItems = CartItem::where('user_id', $user->id)
                                ->with('book:id,price,currency')
                                ->get();

            $totalItems = $cartItems->sum('quantity');
            $totalAmount = $cartItems->sum(function($item) {
                return $item->book->price * $item->quantity;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_items' => $totalItems,
                    'total_amount' => $totalAmount,
                    'currency' => 'MAD'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if book is in cart
     */
    public function checkInCart($bookId)
    {
        try {
            $user = Auth::user();

            $inCart = CartItem::where([
                'user_id' => $user->id,
                'book_id' => $bookId
            ])->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'in_cart' => $inCart
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check cart status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move item from cart to favorites
     */
    public function moveToFavorites($cartItemId)
    {
        try {
            $user = Auth::user();

            $cartItem = CartItem::where('id', $cartItemId)
                               ->where('user_id', $user->id)
                               ->with('book')
                               ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            DB::beginTransaction();

            // Add to favorites if not already there
            $favoriteExists = \App\Models\BookLike::where([
                'user_id' => $user->id,
                'book_id' => $cartItem->book_id
            ])->exists();

            if (!$favoriteExists) {
                \App\Models\BookLike::create([
                    'user_id' => $user->id,
                    'book_id' => $cartItem->book_id
                ]);
            }

            // Remove from cart
            $cartItem->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book moved to favorites successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to move book to favorites',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}