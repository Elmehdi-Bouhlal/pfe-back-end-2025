<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'amount_paid',
        'currency',
        'status',
        'payment_method',
        'transaction_id',
        'payment_details',
        'purchased_at'
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'payment_details' => 'array',
        'purchased_at' => 'datetime'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}