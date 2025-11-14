<?php
// app/Models/PaymentMethod.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'provider_name',
        'details',
        'is_default',
        'is_active',
        'verified_at'
    ];

    protected $casts = [
        'details' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime'
    ];

    protected $hidden = [
        'details' // Hide sensitive payment details
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Mutators
    public function setIsDefaultAttribute($value)
    {
        if ($value) {
            // Remove default from other payment methods of the same user
            static::where('user_id', $this->user_id)
                  ->where('id', '!=', $this->id ?? 0)
                  ->update(['is_default' => false]);
        }
        
        $this->attributes['is_default'] = $value;
    }

    // Accessors
    public function getDisplayNameAttribute()
    {
        switch ($this->type) {
            case 'paypal':
                return 'PayPal (' . ($this->details['email'] ?? 'Non spécifié') . ')';
            case 'cash_on_delivery':
                return 'Paiement à la livraison';
            case 'bank_card':
                return ($this->provider_name ?? 'Carte bancaire') . ' ****' . ($this->details['last_four'] ?? '0000');
            default:
                return ucfirst($this->type);
        }
    }

    public function getIconAttribute()
    {
        switch ($this->type) {
            case 'paypal':
                return 'paypal';
            case 'cash_on_delivery':
                return 'cash';
            case 'bank_card':
                return 'credit-card';
            default:
                return 'payment';
        }
    }
}