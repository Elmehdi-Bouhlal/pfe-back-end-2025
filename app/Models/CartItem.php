<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cart_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'quantity',
        'added_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'added_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'subtotal',
        'formatted_subtotal'
    ];

    /**
     * User relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Book relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get subtotal for this cart item
     *
     * @return float
     */
    public function getSubtotalAttribute(): float
    {
        return $this->book ? ($this->book->price * $this->quantity) : 0;
    }

    /**
     * Get formatted subtotal with currency
     *
     * @return string
     */
    public function getFormattedSubtotalAttribute(): string
    {
        $currency = $this->book ? $this->book->currency : 'MAD';
        return number_format($this->subtotal, 2) . ' ' . $currency;
    }

    /**
     * Scope to get cart items for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get available items (where book is still available)
     */
    public function scopeAvailable($query)
    {
        return $query->whereHas('book', function($q) {
            $q->where('is_available', true)
              ->where('status', 'published');
        });
    }

    /**
     * Check if the book is still available
     *
     * @return bool
     */
    public function isBookAvailable(): bool
    {
        return $this->book && 
               $this->book->is_available && 
               $this->book->status === 'published';
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // Set added_at timestamp when creating
        static::creating(function ($cartItem) {
            $cartItem->added_at = now();
        });

        // Clean up when deleting
        static::deleting(function ($cartItem) {
            // You can add cleanup logic here if needed
        });
    }
}