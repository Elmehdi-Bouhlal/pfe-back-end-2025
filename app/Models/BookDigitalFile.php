<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BookDigitalFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'file_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'is_active',
        'uploaded_at'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    // Relations
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // Accesseurs
    public function getFileSizeHumanAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function getDownloadUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    public function getFileExistsAttribute()
    {
        return Storage::exists($this->file_path);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('file_type', $type);
    }

    // Méthodes
    public function deleteFile()
    {
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
        
        return $this->delete();
    }
}