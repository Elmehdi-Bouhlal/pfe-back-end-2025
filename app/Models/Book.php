<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'description',
        'genre',
        'language',
        'book_type',
        'book_condition',
        'price',
        'original_price',
        'currency',
        'is_available',
        'is_negotiable',
        'user_id',
        'seller_notes',
        'file_path',
        'file_size',
        'file_format',
        'download_count',
        'view_count',
        'like_count',
        'share_count',
        'status',
        'published_at',
        'sold_at',
        'location_city',
        'location_region',
        'location_country',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_negotiable' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'share_count' => 'integer',
        'published_at' => 'datetime',
        'sold_at' => 'datetime',
    ];

    protected $dates = [
        'published_at',
        'sold_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ========== RELATIONS ==========

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function images()
    {
        return $this->hasMany(BookImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(BookImage::class)->where('is_primary', true);
    }

    public function categories()
    {
        return $this->belongsToMany(BookCategory::class, 'book_category_pivot', 'book_id', 'category_id')
                    ->withTimestamps();
    }

    public function likes()
    {
        return $this->hasMany(BookLike::class);
    }

    public function views()
    {
        return $this->hasMany(BookView::class);
    }

    public function conversations()
    {
        return $this->hasMany(BookConversation::class);
    }

    public function transactions()
    {
        return $this->hasMany(BookTransaction::class);
    }

    public function reviews()
    {
        return $this->hasMany(BookReview::class);
    }

    public function reports()
    {
        return $this->hasMany(BookReport::class);
    }

    // ========== SCOPES ==========

    public function scopePublished(Builder $query)
    {
        return $query->where('status', 'published');
    }

    public function scopeAvailable(Builder $query)
    {
        return $query->where('is_available', true)
                    ->where('status', 'published');
    }

    public function scopeByType(Builder $query, string $type)
    {
        return $query->where('book_type', $type);
    }

    public function scopeInLocation(Builder $query, string $city = null, string $region = null)
    {
        if ($city) {
            $query->where('location_city', $city);
        }
        if ($region) {
            $query->where('location_region', $region);
        }
        return $query;
    }

    public function scopeByLanguage(Builder $query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopePriceRange(Builder $query, float $min = null, float $max = null)
    {
        if ($min) {
            $query->where('price', '>=', $min);
        }
        if ($max) {
            $query->where('price', '<=', $max);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('author', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('genre', 'like', "%{$search}%");
        });
    }

    public function scopePopular(Builder $query)
    {
        return $query->orderByDesc('view_count')
                    ->orderByDesc('like_count');
    }

    public function scopeRecent(Builder $query)
    {
        return $query->orderByDesc('published_at');
    }

    // ========== MÉTHODES ==========

    public function isLikedBy(User $user = null): bool
    {
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function incrementLikeCount(): void
    {
        $this->increment('like_count');
    }

    public function decrementLikeCount(): void
    {
        $this->decrement('like_count');
    }

    public function incrementShareCount(): void
    {
        $this->increment('share_count');
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }
        return round((($this->original_price - $this->price) / $this->original_price) * 100, 2);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function getIsDigitalAttribute(): bool
    {
        return $this->book_type === 'digital';
    }

    public function getIsPhysicalAttribute(): bool
    {
        return $this->book_type === 'physical';
    }

    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    public function getTotalReviewsAttribute(): int
    {
        return $this->reviews()->count();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id && $this->status !== 'sold';
    }

    public function markAsSold(): bool
    {
        return $this->update([
            'status' => 'sold',
            'is_available' => false,
            'sold_at' => now(),
        ]);
    }

    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}