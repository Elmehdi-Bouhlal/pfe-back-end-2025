<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadingProgress extends Model
{
    use HasFactory;

    protected $table = 'reading_progress';

    protected $fillable = [
        'user_id',
        'book_id',
        'progress_percentage',
        'current_page',
        'total_reading_time',
        'bookmarks',
        'last_read_at'
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'current_page' => 'integer',
        'total_reading_time' => 'integer',
        'bookmarks' => 'array',
        'last_read_at' => 'datetime'
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
    public function scopeCompleted($query)
    {
        return $query->where('progress_percentage', 100);
    }

    public function scopeInProgress($query)
    {
        return $query->whereBetween('progress_percentage', [1, 99]);
    }

    public function scopeNotStarted($query)
    {
        return $query->where('progress_percentage', 0);
    }

    // Accessors
    public function getIsCompletedAttribute()
    {
        return $this->progress_percentage >= 100;
    }

    public function getFormattedReadingTimeAttribute()
    {
        $hours = floor($this->total_reading_time / 60);
        $minutes = $this->total_reading_time % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        
        return $minutes . 'm';
    }

    // Methods
    public function addBookmark($pageNumber)
    {
        $bookmarks = $this->bookmarks ?? [];
        if (!in_array($pageNumber, $bookmarks)) {
            $bookmarks[] = $pageNumber;
            $this->update(['bookmarks' => $bookmarks]);
        }
    }

    public function removeBookmark($pageNumber)
    {
        $bookmarks = $this->bookmarks ?? [];
        $bookmarks = array_diff($bookmarks, [$pageNumber]);
        $this->update(['bookmarks' => array_values($bookmarks)]);
    }

    public function updateProgress($percentage, $currentPage = null, $additionalTime = 0)
    {
        $data = [
            'progress_percentage' => max(0, min(100, $percentage)),
            'last_read_at' => now()
        ];

        if ($currentPage) {
            $data['current_page'] = $currentPage;
        }

        if ($additionalTime > 0) {
            $data['total_reading_time'] = $this->total_reading_time + $additionalTime;
        }

        return $this->update($data);
    }
}