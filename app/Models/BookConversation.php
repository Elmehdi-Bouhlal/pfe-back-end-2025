<?php

// app/Models/BookConversation.php - Version mise à jour
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'buyer_id',
        'seller_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages()
    {
        return $this->hasMany(BookMessage::class, 'conversation_id')
                    ->orderBy('created_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(BookMessage::class, 'conversation_id')
                    ->latest('created_at');
    }
    // ========== SCOPES POUR L'API ==========

    public function scopeForUser($query, $userId)
    {
        return $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithUnreadFor($query, $userId)
    {
        return $query->whereHas('messages', function($q) use ($userId) {
            $q->where('sender_id', '!=', $userId)
              ->where('is_read', false);
        });
    }

    // ========== MÉTHODES EXISTANTES ADAPTÉES ==========

    public function getOtherParticipant(User $currentUser): User
    {
        return $currentUser->id === $this->buyer_id ? $this->seller : $this->buyer;
    }

    // Alias pour compatibilité avec l'API
    public function getOtherUser($currentUserId)
    {
        return $this->buyer_id === $currentUserId ? $this->seller : $this->buyer;
    }

    public function hasUnreadMessagesFor(User $user): bool
    {
        return $this->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->exists();
    }

    // Alias pour compatibilité avec l'API
    public function isUnreadFor($userId): bool
    {
        return $this->messages()
                    ->where('sender_id', '!=', $userId)
                    ->where('is_read', false)
                    ->exists();
    }

    public function getUnreadCountFor(User $user): int
    {
        return $this->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count();
    }

    public function markAsRead(User $user): void
    {
        $this->messages()
             ->where('sender_id', '!=', $user->id)
             ->where('is_read', false)
             ->update([
                 'is_read' => true,
                 'read_at' => now(),
             ]);
    }

    // Surcharge pour accepter aussi l'ID utilisateur
    public function markAsReadFor($userId): void
    {
        $this->messages()
             ->where('sender_id', '!=', $userId)
             ->where('is_read', false)
             ->update([
                 'is_read' => true,
                 'read_at' => now(),
             ]);
    }

    public function updateLastMessage(): void
    {
        $this->update([
            'last_message_at' => $this->latestMessage?->created_at ?? now(),
        ]);
    }
}