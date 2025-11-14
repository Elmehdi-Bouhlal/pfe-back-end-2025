<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'book_id',
        'book_title',
        'book_author',
        'unit_price',
        'quantity',
        'total_price',
        'book_type',
        'book_condition',
        'book_metadata',
        'download_link',
        'download_count',
        'download_limit',
        'download_expires_at'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'book_metadata' => 'array',
        'download_expires_at' => 'datetime'
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_price, 2) . ' ' . ($this->order->currency ?? 'MAD');
    }

    public function getCanDownloadAttribute()
    {
        if ($this->book_type !== 'digital') {
            return false;
        }

        if ($this->download_limit && $this->download_count >= $this->download_limit) {
            return false;
        }

        if ($this->download_expires_at && $this->download_expires_at->isPast()) {
            return false;
        }

        return $this->order->payment_status === 'completed';
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }
}