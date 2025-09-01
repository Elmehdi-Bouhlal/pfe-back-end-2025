<?php

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
                    ->latestOfMany();
    }

    // ========== MÉTHODES ==========

    public function getOtherParticipant(User $currentUser): User
    {
        return $currentUser->id === $this->buyer_id ? $this->seller : $this->buyer;
    }

    public function hasUnreadMessagesFor(User $user): bool
    {
        return $this->messages()
                    ->where('sender_id', '!=', $user->id)
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

    public function updateLastMessage(): void
    {
        $this->update([
            'last_message_at' => $this->latestMessage?->created_at ?? now(),
        ]);
    }
}