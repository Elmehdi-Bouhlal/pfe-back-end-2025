<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'message_type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // ========== RELATIONS ==========

    public function conversation()
    {
        return $this->belongsTo(BookConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ========== ÉVÉNEMENTS ==========

    protected static function boot()
    {
        parent::boot();

        static::created(function ($message) {
            $message->conversation->updateLastMessage();
        });
    }

    // ========== MÉTHODES ==========

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function isFromBuyer(): bool
    {
        return $this->sender_id === $this->conversation->buyer_id;
    }

    public function isFromSeller(): bool
    {
        return $this->sender_id === $this->conversation->seller_id;
    }
}