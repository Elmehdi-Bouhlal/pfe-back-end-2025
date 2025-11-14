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

    // ========== SCOPES POUR L'API ==========

    public function scopeUserMessages($query)
    {
        return $query; // Tous les messages sont des messages utilisateur dans votre système
    }

    // ========== ÉVÉNEMENTS ==========

    protected static function boot()
    {
        parent::boot();

        static::created(function ($message) {
            $message->conversation->updateLastMessage();
        });
    }

    // ========== MÉTHODES EXISTANTES ==========

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

    // ========== MÉTHODES POUR L'API ==========

    public function isFromUser($userId): bool
    {
        return $this->sender_id === $userId;
    }

    public function hasAttachments(): bool
    {
        // Votre DB n'a pas de champ attachments, retourne false pour l'instant
        return false;
    }
}