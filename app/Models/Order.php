<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'shipping_cost',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'promo_code',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'payment_details',
        'payment_completed_at',
        'shipping_address',
        'billing_address',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_details' => 'array',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'metadata' => 'array',
        'payment_completed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Methods
    public static function generateOrderNumber()
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8));
        } while (static::where('order_number', $orderNumber)->exists());
        
        return $orderNumber;
    }

    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2) . ' ' . $this->currency;
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En cours de traitement',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            'refunded' => 'Remboursée'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'processing' => 'En cours',
            'completed' => 'Payé',
            'failed' => 'Échec',
            'refunded' => 'Remboursé'
        ];
        
        return $labels[$this->payment_status] ?? ucfirst($this->payment_status);
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed']) && 
               in_array($this->payment_status, ['pending', 'processing']);
    }

    public function markAsPaid($transactionId = null, $details = [])
    {
        $this->update([
            'payment_status' => 'completed',
            'payment_transaction_id' => $transactionId,
            'payment_completed_at' => now(),
            'payment_details' => array_merge($this->payment_details ?? [], $details),
            'status' => $this->status === 'pending' ? 'confirmed' : $this->status
        ]);
    }
}

