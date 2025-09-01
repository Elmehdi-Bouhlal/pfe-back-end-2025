<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'seller_id',
        'buyer_id',
        'agreed_price',
        'original_price',
        'currency',
        'status',
        'transaction_date',
        'completion_date',
        'delivery_method',
        'delivery_address',
        'tracking_number',
        'seller_notes',
        'buyer_notes',
        'admin_notes',
    ];

    protected $casts = [
        'agreed_price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'transaction_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function reviews()
    {
        return $this->hasMany(BookReview::class, 'transaction_id');
    }

    // ========== MÉTHODES ==========

    public function getFormattedAgreedPriceAttribute(): string
    {
        return number_format($this->agreed_price, 2) . ' ' . $this->currency;
    }

    public function getSavingsAttribute(): float
    {
        return $this->original_price - $this->agreed_price;
    }

    public function getSavingsPercentageAttribute(): float
    {
        if ($this->original_price <= 0) return 0;
        return round(($this->getSavingsAttribute() / $this->original_price) * 100, 2);
    }

    public function canBeCancelledBy(User $user): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) &&
               ($this->buyer_id === $user->id || $this->seller_id === $user->id);
    }

    public function complete(): bool
    {
        $success = $this->update([
            'status' => 'completed',
            'completion_date' => now(),
        ]);

        if ($success) {
            $this->book->markAsSold();
        }

        return $success;
    }

    public function cancel(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }
}