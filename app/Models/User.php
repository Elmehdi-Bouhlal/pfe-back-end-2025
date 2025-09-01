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

    // ========== MÉTHODES UTILES ==========

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
}