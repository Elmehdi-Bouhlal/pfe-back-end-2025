<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'content',
        'page_number',
        'chapter',
        'note_type',
        'metadata'
    ];

    protected $casts = [
        'page_number' => 'integer',
        'metadata' => 'array'
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
    public function scopeByType($query, $type)
    {
        return $query->where('note_type', $type);
    }

    public function scopeByPage($query, $page)
    {
        return $query->where('page_number', $page);
    }

    public function scopeByChapter($query, $chapter)
    {
        return $query->where('chapter', $chapter);
    }

    public function scopeHighlights($query)
    {
        return $query->where('note_type', 'highlight');
    }

    public function scopeNotes($query)
    {
        return $query->where('note_type', 'note');
    }

    public function scopeBookmarks($query)
    {
        return $query->where('note_type', 'bookmark');
    }

    public function scopeQuestions($query)
    {
        return $query->where('note_type', 'question');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getFormattedContentAttribute()
    {
        $content = $this->content;
        
        // Truncate long content for display
        if (strlen($content) > 200) {
            return substr($content, 0, 200) . '...';
        }
        
        return $content;
    }

    public function getColorAttribute()
    {
        $colors = [
            'highlight' => '#FFD700', // Gold
            'note' => '#87CEEB',       // Sky Blue
            'bookmark' => '#90EE90',   // Light Green
            'question' => '#FFB6C1'    // Light Pink
        ];

        return $colors[$this->note_type] ?? '#D3D3D3'; // Default gray
    }

    public function getIconAttribute()
    {
        $icons = [
            'highlight' => 'highlight',
            'note' => 'note',
            'bookmark' => 'bookmark',
            'question' => 'help'
        ];

        return $icons[$this->note_type] ?? 'note';
    }

    // Methods
    public function updateContent($newContent)
    {
        return $this->update(['content' => $newContent]);
    }

    public function addMetadata($key, $value)
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        return $this->update(['metadata' => $metadata]);
    }

    public function getMetadata($key, $default = null)
    {
        $metadata = $this->metadata ?? [];
        return $metadata[$key] ?? $default;
    }

    // Static methods
    public static function getAvailableTypes()
    {
        return [
            'highlight' => 'Highlight',
            'note' => 'Note',
            'bookmark' => 'Bookmark',
            'question' => 'Question'
        ];
    }

    public static function createForPage($userId, $bookId, $pageNumber, $content, $type = 'note')
    {
        return self::create([
            'user_id' => $userId,
            'book_id' => $bookId,
            'page_number' => $pageNumber,
            'content' => $content,
            'note_type' => $type
        ]);
    }
}