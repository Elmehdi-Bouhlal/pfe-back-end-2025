<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'reporter_id',
        'report_reason',
        'description',
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    // ========== MÉTHODES ==========

    public function resolve(string $adminNotes = null): bool
    {
        return $this->update([
            'status' => 'resolved',
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
        ]);
    }

    public function dismiss(string $adminNotes = null): bool
    {
        return $this->update([
            'status' => 'dismissed',
            'admin_notes' => $adminNotes,
            'resolved_at' => now(),
        ]);
    }

    public function getReasonLabelAttribute(): string
    {
        $reasons = [
            'inappropriate' => 'Contenu inapproprié',
            'spam' => 'Spam',
            'fraud' => 'Fraude',
            'copyright' => 'Violation de droits d\'auteur',
            'other' => 'Autre',
        ];

        return $reasons[$this->report_reason] ?? 'Inconnu';
    }
}