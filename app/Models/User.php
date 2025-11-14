<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        "avatar",        
        "phone",
        "last_name",
        "adress",
        "notifications",
        "email_verified_at",
        "plan_id",
        "package_id",
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ========== RELATIONS POUR LES BOOKS ==========

    /**
     * Books owned by this user (as seller)
     */
    public function books()
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Books liked by this user
     */
    public function likedBooks()
    {
        return $this->belongsToMany(Book::class, 'book_likes')
                    ->withTimestamps();
    }

    /**
     * Book likes made by this user
     */
    public function bookLikes()
    {
        return $this->hasMany(BookLike::class);
    }

    /**
     * Book views made by this user
     */
    public function bookViews()
    {
        return $this->hasMany(BookView::class);
    }

    /**
     * Conversations as buyer
     */
    public function buyerConversations()
    {
        return $this->hasMany(BookConversation::class, 'buyer_id');
    }

    /**
     * Conversations as seller
     */
    public function sellerConversations()
    {
        return $this->hasMany(BookConversation::class, 'seller_id');
    }

    /**
     * All conversations (buyer + seller)
     */
    public function conversations()
    {
        return BookConversation::where('buyer_id', $this->id)
                              ->orWhere('seller_id', $this->id);
    }

    /**
     * Messages sent by this user
     */
    public function sentMessages()
    {
        return $this->hasMany(BookMessage::class, 'sender_id');
    }

    /**
     * Transactions as buyer
     */
    public function purchases()
    {
        return $this->hasMany(BookTransaction::class, 'buyer_id');
    }

    /**
     * Transactions as seller
     */
    public function sales()
    {
        return $this->hasMany(BookTransaction::class, 'seller_id');
    }

    /**
     * Reviews written by this user
     */
    public function writtenReviews()
    {
        return $this->hasMany(BookReview::class, 'reviewer_id');
    }

    /**
     * Reviews received by this user
     */
    public function receivedReviews()
    {
        return $this->hasMany(BookReview::class, 'reviewed_user_id');
    }

    /**
     * Reports made by this user
     */
    public function reports()
    {
        return $this->hasMany(BookReport::class, 'reporter_id');
    }

    /**
     * Cart items belonging to this user
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Books in user's cart
     */
    public function cartBooks()
    {
        return $this->belongsToMany(Book::class, 'cart_items')
                    ->withPivot(['quantity', 'added_at'])
                    ->withTimestamps();
    }

    // ========== MÉTHODES MANQUANTES POUR LE PROFIL ==========

    /**
     * Get user's average rating from all reviews received
     * Cette méthode calcule la note moyenne de l'utilisateur
     */
    public function averageRating(): float
    {
        return $this->receivedReviews()->avg('rating') ?? 0.0;
    }

    /**
     * Get all reviews received by this user
     * Alias pour receivedReviews() pour compatibilité avec le controller
     */
    public function reviewsReceived()
    {
        return $this->receivedReviews();
    }

    /**
     * Calculate total earnings from completed sales
     * Calcule les gains totaux de l'utilisateur
     */
    public function calculateTotalEarnings(): float
    {
        return $this->sales()
                   ->where('status', 'completed')
                   ->sum('amount') ?? 0.0;
    }

    // ========== MÉTHODES UTILES EXISTANTES ==========

    /**
     * Check if user has liked a specific book
     */
    public function hasLikedBook(Book $book): bool
    {
        return $this->likedBooks()->where('book_id', $book->id)->exists();
    }

    /**
     * Get user's average rating as seller
     */
    public function getSellerRatingAttribute(): float
    {
        return $this->receivedReviews()->where('review_type', 'seller')->avg('rating') ?? 0;
    }

    /**
     * Get total books sold
     */
    public function getTotalBooksSoldAttribute(): int
    {
        return $this->sales()->where('status', 'completed')->count();
    }

    /**
     * Get total books bought
     */
    public function getTotalBooksBoughtAttribute(): int
    {
        return $this->purchases()->where('status', 'completed')->count();
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->name . ($this->last_name ? ' ' . $this->last_name : '');
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar 
            ? asset('storage/' . $this->avatar) 
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=random';
    }

    // ========== MÉTHODES ADDITIONNELLES POUR LES STATISTIQUES ==========

    /**
     * Get count of books currently listed for sale
     */
    public function getBooksListedCountAttribute(): int
    {
        return $this->books()->where('status', 'published')->count();
    }

    /**
     * Get count of books sold
     */
    public function getBooksSoldCountAttribute(): int
    {
        return $this->books()->where('status', 'sold')->count();
    }

    /**
     * Get count of favorite books
     */
    public function getFavoriteBooksCountAttribute(): int
    {
        return $this->likedBooks()->count();
    }

    /**
     * Get count of items in cart
     */
    public function getCartItemsCountAttribute(): int
    {
        return $this->cartItems()->count();
    }

    /**
     * Get user statistics for profile
     */
    public function getProfileStats(): array
    {
        return [
            'booksListed' => $this->books()->where('status', 'published')->count(),
            'booksSold' => $this->books()->where('status', 'sold')->count(),
            'rating' => $this->averageRating(),
            'reviews' => $this->reviewsReceived()->count(),
            'totalEarnings' => $this->calculateTotalEarnings(),
            'favoriteBooks' => $this->likedBooks()->count(),
            'cartItems' => $this->cartItems()->count(),
        ];
    }

    /**
     * Check if user can be rated (has completed transactions)
     */
    public function canBeRated(): bool
    {
        return $this->sales()->where('status', 'completed')->exists() ||
               $this->purchases()->where('status', 'completed')->exists();
    }

    /**
     * Get user's reputation level based on rating and transaction count
     */
    public function getReputationLevel(): string
    {
        $rating = $this->averageRating();
        $transactionCount = $this->sales()->where('status', 'completed')->count();

        if ($rating >= 4.5 && $transactionCount >= 50) {
            return 'Expert';
        } elseif ($rating >= 4.0 && $transactionCount >= 20) {
            return 'Confirmé';
        } elseif ($rating >= 3.5 && $transactionCount >= 5) {
            return 'Expérimenté';
        } elseif ($transactionCount >= 1) {
            return 'Débutant';
        } else {
            return 'Nouveau';
        }
    }

    /**
     * Get user's activity summary
     */
    public function getActivitySummary(): array
    {
        return [
            'totalTransactions' => $this->sales()->count() + $this->purchases()->count(),
            'completedSales' => $this->sales()->where('status', 'completed')->count(),
            'completedPurchases' => $this->purchases()->where('status', 'completed')->count(),
            'averageRating' => $this->averageRating(),
            'totalReviews' => $this->reviewsReceived()->count(),
            'reputationLevel' => $this->getReputationLevel(),
            'memberSince' => $this->created_at->format('Y-m-d'),
            'lastActivity' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}