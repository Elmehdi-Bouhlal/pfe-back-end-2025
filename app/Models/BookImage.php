<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BookImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'image_path',
        'image_name',
        'image_size',
        'image_type',
        'sort_order',
        'is_primary',
        'alt_text',
    ];

    protected $casts = [
        'image_size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    // ========== RELATIONS ==========

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // ========== MÉTHODES ==========

    public function getFullUrlAttribute(): string
    {
        return Storage::url($this->image_path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->image_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function setPrimary(): bool
    {
        // Retirer le statut primary des autres images du même livre
        self::where('book_id', $this->book_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Définir cette image comme principale
        return $this->update(['is_primary' => true]);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            // Supprimer le fichier physique
            if (Storage::exists($image->image_path)) {
                Storage::delete($image->image_path);
            }
        });
    }
}