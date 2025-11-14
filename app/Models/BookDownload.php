<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'ip_address',
        'user_agent',
        'download_type',
        'file_size',
        'download_completed'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'download_completed' => 'boolean'
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
        return $query->where('download_completed', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('download_completed', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('download_type', $type);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->where('created_at', '>=', now()->startOfWeek());
    }

    public function scopeThisMonth($query)
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    // Accessors
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function getDownloadStatusAttribute()
    {
        return $this->download_completed ? 'Completed' : 'Failed';
    }

    public function getBrowserAttribute()
    {
        $userAgent = $this->user_agent;
        
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'Edge')) {
            return 'Edge';
        }
        
        return 'Unknown';
    }

    public function getDeviceTypeAttribute()
    {
        $userAgent = $this->user_agent;
        
        if (str_contains($userAgent, 'Mobile')) {
            return 'Mobile';
        } elseif (str_contains($userAgent, 'Tablet')) {
            return 'Tablet';
        }
        
        return 'Desktop';
    }

    // Methods
    public function markAsCompleted()
    {
        return $this->update(['download_completed' => true]);
    }

    public function markAsFailed()
    {
        return $this->update(['download_completed' => false]);
    }

    // Static methods
    public static function logDownload($userId, $bookId, $fileSize = null, $downloadType = 'pdf')
    {
        return self::create([
            'user_id' => $userId,
            'book_id' => $bookId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'download_type' => $downloadType,
            'file_size' => $fileSize,
            'download_completed' => true
        ]);
    }

    public static function getDownloadStats($userId = null)
    {
        $query = self::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return [
            'total_downloads' => $query->count(),
            'completed_downloads' => $query->completed()->count(),
            'failed_downloads' => $query->failed()->count(),
            'total_bandwidth' => $query->sum('file_size'),
            'downloads_today' => $query->today()->count(),
            'downloads_this_week' => $query->thisWeek()->count(),
            'downloads_this_month' => $query->thisMonth()->count(),
        ];
    }

    public static function getPopularBooks($limit = 10)
    {
        return self::select('book_id', \DB::raw('count(*) as download_count'))
                   ->with('book')
                   ->groupBy('book_id')
                   ->orderBy('download_count', 'desc')
                   ->limit($limit)
                   ->get();
    }

    public static function getUserDownloadHistory($userId, $limit = 50)
    {
        return self::where('user_id', $userId)
                   ->with(['book' => function($query) {
                       $query->select('id', 'title', 'author', 'book_type');
                   }])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }
}